<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What kind of thing an item is.
 *
 * Not a topic — a *form*. A tender and an earnings release are both economic
 * news, and they reach a reader's decision by completely different routes, so
 * the form is what the importance score and the routing rules key on.
 */
enum DocumentType: string
{
    case Decision = 'decision';
    case Regulation = 'regulation';
    case Tender = 'tender';
    case Licence = 'licence';
    case Funding = 'funding';
    case Appointment = 'appointment';
    case Earnings = 'earnings';
    case Statistic = 'statistic';
    case Announcement = 'announcement';
    case Report = 'report';

    /*
     * The three non-institutional forms.
     *
     * The ten cases above are all things an institution publishes, which is
     * what the classifier was built to read. Measured against a real business
     * feed, half the misses were none of them: prices moving, someone
     * forecasting, and an executive saying something. A reader's decision is
     * moved by all three, so the inbox carries them — but they are not
     * documents, and the weights below say so.
     */
    case MarketMove = 'market_move';
    case Forecast = 'forecast';
    case Statement = 'statement';

    case Other = 'other';

    public function label(): string
    {
        return __('enums.document_type.'.$this->value);
    }

    /**
     * How much this form of document moves the importance score, 0–1.
     *
     * A decision or a regulation changes what a business may do. An
     * announcement usually does not.
     *
     * The three non-institutional forms sit deliberately low. A market move is
     * a fact, but a transient one a market participant usually knows before we
     * do. A forecast is an opinion about the future. A statement is often
     * positioning. None of them binds anyone to anything, which is what
     * separates them from a decision — so none of them scores like one.
     */
    public function weight(): float
    {
        return match ($this) {
            self::Decision, self::Regulation => 1.0,
            self::Tender, self::Licence, self::Funding => 0.9,
            self::Earnings, self::Statistic => 0.7,
            self::MarketMove => 0.6,
            self::Report, self::Appointment => 0.5,
            self::Announcement, self::Forecast, self::Statement => 0.4,
            self::Other => 0.3,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $c, self $case): array => $c + [$case->value => $case->label()],
            [],
        );
    }
}
