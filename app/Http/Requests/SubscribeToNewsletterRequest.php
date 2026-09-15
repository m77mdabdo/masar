<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The first Form Request in this codebase, and the shape the rest should take.
 *
 * Until now the only external input on the public site was a search string, so
 * CLAUDE.md §5's rule sat unmet without consequence. A POST changes that: this
 * is the first place a stranger's input reaches a write.
 *
 * The honeypot lives here rather than in the action because it is a property of
 * the submission, not of subscribing — and because a rejected bot should look
 * exactly like a validation failure, not like a different code path.
 */
class SubscribeToNewsletterRequest extends FormRequest
{
    /** The field no human sees and every naive bot fills. */
    public const HONEYPOT = 'company_website';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // `email:rfc` only — not `dns`. A DNS lookup on a request path adds
            // a network round trip the visitor waits on, and fails closed on a
            // resolver hiccup, which would reject real addresses.
            'email' => ['required', 'string', 'email:rfc', 'max:254'],

            // Present-and-empty is what a real browser sends; anything else is
            // a bot that filled every input it found.
            self::HONEYPOT => ['nullable', 'string', 'max:0'],

            'source' => ['nullable', 'string', Rule::in(['band', 'page'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            // Addresses are compared for uniqueness, so they are stored in one
            // case. The local part is technically case-sensitive; no mail
            // provider a reader will use treats it that way.
            $this->merge(['email' => mb_strtolower(trim($email))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'أدخل بريدك الإلكتروني.',
            'email.email' => 'هذا البريد الإلكتروني غير صالح.',
            'email.max' => 'هذا البريد الإلكتروني طويل أكثر من اللازم.',
            self::HONEYPOT.'.max' => 'تعذّر إرسال النموذج.',
        ];
    }

    public function attributes(): array
    {
        return ['email' => 'البريد الإلكتروني'];
    }
}
