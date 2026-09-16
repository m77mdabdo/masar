<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Company;
use App\Models\Person;
use Illuminate\Support\Facades\Cache;

/**
 * Which known entities a piece of text names.
 *
 * Deliberately dumb: it matches names we already hold against normalised text.
 * It infers nothing, and it is not an attempt at named-entity recognition — a
 * company this system has never heard of stays unknown, which is the honest
 * answer rather than a guess.
 *
 * Used to pre-fill the entity mentions on a draft made from the inbox. That a
 * headline names Aramco is a fact about the headline, so it carries over; what
 * the story says about Aramco is writing, so it does not.
 *
 * The cache is **tagged**. `ImportanceScorer` keeps its own untagged copy of the
 * company list, which the stale-snapshot register flags as invisible to every
 * tag flush we have; the two should collapse into this once the scorer's
 * measured output can be re-verified.
 */
final class EntityMatcher
{
    private const TTL_SECONDS = 3600;

    /** Below this a "name" matches half the language. */
    private const MIN_NAME_LENGTH = 3;

    public function __construct(private readonly ArabicNormaliser $normaliser) {}

    /**
     * @return array<int, array{type: string, id: int}>
     */
    public function __invoke(?string ...$text): array
    {
        $haystack = ($this->normaliser)(implode(' ', array_filter($text)));

        if ($haystack === '') {
            return [];
        }

        $found = [];

        foreach ($this->index() as $entry) {
            if (str_contains($haystack, $entry['name'])) {
                // One mention per entity however many of its names matched.
                $found[$entry['type'].':'.$entry['id']] = ['type' => $entry['type'], 'id' => $entry['id']];
            }
        }

        return array_values($found);
    }

    /**
     * @return array<int, array{type: string, id: int, name: string}>
     */
    private function index(): array
    {
        return Cache::tags(['entities'])->remember(
            'entities:name-index',
            self::TTL_SECONDS,
            function (): array {
                $index = [];

                foreach (Company::query()->with('translations')->get() as $company) {
                    foreach ($company->translations->pluck('name')->push($company->ticker) as $name) {
                        $this->add($index, 'company', (int) $company->getKey(), (string) $name);
                    }
                }

                foreach (Person::query()->with('translations')->get() as $person) {
                    foreach ($person->translations->pluck('name') as $name) {
                        $this->add($index, 'person', (int) $person->getKey(), (string) $name);
                    }
                }

                return $index;
            },
        );
    }

    /**
     * @param  array<int, array{type: string, id: int, name: string}>  $index
     */
    private function add(array &$index, string $type, int $id, string $name): void
    {
        $normalised = ($this->normaliser)($name);

        if (mb_strlen($normalised) < self::MIN_NAME_LENGTH) {
            return;
        }

        $index[] = ['type' => $type, 'id' => $id, 'name' => $normalised];
    }
}
