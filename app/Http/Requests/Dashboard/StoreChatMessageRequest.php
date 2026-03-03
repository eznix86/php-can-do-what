<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreChatMessageRequest extends FormRequest
{
    private const BOT_PREFIX_PATTERN = '/^(\/bot|@(?<agent>[a-z0-9_-]+))\b\s*/i';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'],
            'client_message_id' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Please enter a message before sending.',
            'message.max' => 'Messages can not be longer than 1000 characters.',
        ];
    }

    public function isPromptingBot(): bool
    {
        return preg_match(self::BOT_PREFIX_PATTERN, $this->messageText()) === 1;
    }

    public function prompt(): string
    {
        if (! $this->isPromptingBot()) {
            return '';
        }

        return (string) preg_replace(self::BOT_PREFIX_PATTERN, '', $this->messageText());
    }

    public function agentKey(): ?string
    {
        if (! $this->isPromptingBot()) {
            return null;
        }

        if (str($this->messageText())->lower()->startsWith('/bot')) {
            return 'bot';
        }

        preg_match(self::BOT_PREFIX_PATTERN, $this->messageText(), $matches);

        if (! isset($matches['agent']) || $matches['agent'] === '') {
            return null;
        }

        return strtolower($matches['agent']);
    }

    public function agent(): ?string
    {
        $agentKey = $this->agentKey();

        if ($agentKey === null) {
            return null;
        }

        $agentClass = collect(config('agents.chat', []))->get($agentKey);

        return is_string($agentClass) ? $agentClass : null;
    }

    public function chatMessage(): string
    {
        if (! $this->isPromptingBot()) {
            return $this->messageText();
        }

        $prompt = $this->prompt();

        if ($prompt === '') {
            return '';
        }

        $agentKey = $this->agentKey() ?? 'bot';

        return sprintf('@%s %s', $agentKey, $prompt);
    }

    public function messageId(): string
    {
        $clientMessageId = trim($this->string('client_message_id')->toString());

        return filled($clientMessageId) ? $clientMessageId : Str::uuid()->toString();
    }

    private function messageText(): string
    {
        return trim($this->string('message')->toString());
    }
}
