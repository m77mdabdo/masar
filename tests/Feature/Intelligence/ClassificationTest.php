<?php

declare(strict_types=1);

use App\Enums\ClassificationPath;
use App\Enums\DocumentType;
use App\Models\Source;
use App\Services\Ai\AiProvider;
use App\Services\Ai\NullAiProvider;
use App\Services\Intelligence\Classifier;

it('classifies by keyword and records the rules path with its evidence', function (): void {
    $result = app(Classifier::class)->classify(
        'الوزارة تعتمد تعديلات على لوائح التراخيص الصناعية',
        null,
        Source::factory()->create(),
    );

    expect($result['type'])->toBe(DocumentType::Regulation)
        ->and($result['path'])->toBe(ClassificationPath::Rules)
        // The evidence is why an editor can disagree with the machine.
        ->and($result['evidence']['matched'])->not->toBeEmpty();
});

it('weights the title above the summary', function (): void {
    $result = app(Classifier::class)->classify(
        'طرح مناقصة عامة لتطوير بنية تحتية',
        'ورد ذلك في تقرير صادر عن الجهة',
        Source::factory()->create(),
    );

    // "مناقصة" in the headline beats "تقرير" in the body: the headline carries
    // the form of the document.
    expect($result['type'])->toBe(DocumentType::Tender);
});

it('folds arabic before matching so diacritics do not defeat a keyword', function (): void {
    $result = app(Classifier::class)->classify('صدر قَرَارٌ جديد بشأن النشاط', null, Source::factory()->create());

    expect($result['path'])->toBe(ClassificationPath::Rules)
        ->and($result['type'])->toBe(DocumentType::Decision);
});

it('falls back to the source category when the wording is unfamiliar', function (): void {
    $source = Source::factory()->create(['category' => DocumentType::Tender->value]);

    $result = app(Classifier::class)->classify('نص لا يحتوي أي كلمة مفتاحية معروفة', null, $source);

    expect($result['type'])->toBe(DocumentType::Tender)
        ->and($result['evidence']['from'])->toBe('source_category');
});

it('records unclassified rather than defaulting to something plausible', function (): void {
    $result = app(Classifier::class)->classify('نص لا يحتوي أي كلمة مفتاحية معروفة', null, Source::factory()->create());

    // This is the number that sizes the AI decision. Guessing a type here would
    // quietly delete the evidence.
    expect($result['path'])->toBe(ClassificationPath::Unclassified)
        ->and($result['type'])->toBe(DocumentType::Other);
});

it('ships a provider that declines everything', function (): void {
    $provider = app(AiProvider::class);

    expect($provider)->toBeInstanceOf(NullAiProvider::class)
        ->and($provider->classify('أي عنوان'))->toBeNull()
        ->and($provider->summarize('أي نص'))->toBeNull()
        ->and($provider->suggestHeadline('أي نص'))->toBeNull()
        ->and($provider->extractEntities('أي نص'))->toBe([]);
});

it('takes the ai answer and records the ai path when one is bound', function (): void {
    // Proves the seam works before any provider exists — otherwise the first
    // real provider would be integrated against untested code.
    app()->bind(AiProvider::class, fn (): AiProvider => new class implements AiProvider
    {
        public function summarize(string $text, string $locale = 'ar'): ?string
        {
            return null;
        }

        public function classify(string $title, ?string $summary = null): ?array
        {
            return ['document_type' => 'earnings', 'confidence' => 0.82];
        }

        public function extractEntities(string $text): array
        {
            return [];
        }

        public function suggestHeadline(string $text, string $locale = 'ar'): ?string
        {
            return null;
        }
    });

    $result = app(Classifier::class)->classify('نص لا يحتوي أي كلمة مفتاحية معروفة', null, Source::factory()->create());

    expect($result['type'])->toBe(DocumentType::Earnings)
        ->and($result['path'])->toBe(ClassificationPath::Ai)
        ->and($result['evidence']['confidence'])->toBe(0.82);
});
