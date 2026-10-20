<?php

namespace App\Policies;

/**
 * What one button on an inquiry card is: shown or not, and when shown, whether
 * it can be pressed and, if not, why.
 */
final readonly class InquiryActionState
{
    public function __construct(
        public bool $visible,
        public bool $enabled,
        public ?string $reason = null,
    ) {}

    public static function enabled(): self
    {
        return new self(visible: true, enabled: true);
    }

    public static function disabled(string $reason): self
    {
        return new self(visible: true, enabled: false, reason: $reason);
    }

    public static function hidden(): self
    {
        return new self(visible: false, enabled: false);
    }

    /**
     * The shape the page reads. `reason` is only present on a button that is
     * shown and cannot be pressed.
     *
     * @return array{visible: bool, enabled: bool, reason?: string}
     */
    public function toArray(): array
    {
        return [
            'visible' => $this->visible,
            'enabled' => $this->enabled,
            ...($this->visible && ! $this->enabled && $this->reason !== null ? ['reason' => $this->reason] : []),
        ];
    }
}
