<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Translation\ApproveTranslationFile;
use App\Actions\Translation\SyncTranslationReviews;
use App\Models\TranslationReview;
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

/**
 * Records a person's review of one file. The reviewer is a real account,
 * named on the command line; there is no default and no "system" reviewer.
 */
class ApproveTranslationFileCommand extends Command
{
    protected $signature = 'masar:translations:approve {locale} {file : e.g. site.php} {--reviewer= : the reviewer\'s account email}';

    protected $description = 'Record that a named reviewer approved one interface translation file';

    public function handle(SyncTranslationReviews $sync, ApproveTranslationFile $approve): int
    {
        $sync();

        $reviewer = User::query()->where('email', (string) $this->option('reviewer'))->first();
        if ($reviewer === null) {
            $this->error('No such reviewer account. Pass --reviewer=<email> of a real user.');

            return self::FAILURE;
        }

        $path = $this->argument('file') === 'json'
            ? "lang/{$this->argument('locale')}.json"
            : "lang/{$this->argument('locale')}/{$this->argument('file')}";

        $review = TranslationReview::query()->where('locale', $this->argument('locale'))->where('path', $path)->first();
        if ($review === null) {
            $this->error("No translation file {$path}.");

            return self::FAILURE;
        }

        try {
            $approve($review, $reviewer);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$path} approved by {$reviewer->email}.");

        return self::SUCCESS;
    }
}
