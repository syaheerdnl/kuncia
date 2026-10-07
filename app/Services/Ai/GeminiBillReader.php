<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a utility bill image/PDF to Gemini and gets structured fields back.
 * The landlord always confirms the result before anything is charged.
 *
 * @see https://ai.google.dev/gemini-api/docs/structured-output
 */
class GeminiBillReader
{
    private const PROMPT = <<<'TXT'
    You read Malaysian utility bills (e.g. TNB electricity, SAMB / Air Selangor / other water, Indah Water sewerage).
    Extract the fields from this bill. Rules:
    - amount = the total amount the customer must pay for THIS bill in RM ("Jumlah Perlu Dibayar" / "Amount Due"), as a number.
    - type = "electricity", "water" or "sewerage".
    - period = billing month as YYYY-MM (the month the usage is for).
    - usage = consumption number (kWh for electricity, m3 for water), null if not shown.
    - Use null for anything you cannot read. Never guess numbers.
    TXT;

    public function configured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * @return array{provider: string|null, type: string|null, account_no: string|null, period: string|null, amount: float, usage: float|null, usage_unit: string|null, due_date: string|null}
     */
    public function read(string $contents, string $mimeType): array
    {
        if (! $this->configured()) {
            throw new BillReadException('AI bill reading is not set up (GEMINI_API_KEY missing).');
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
                ->timeout(45)
                ->post('https://generativelanguage.googleapis.com/v1beta/interactions', [
                    'model' => (string) config('services.gemini.model'),
                    'input' => [
                        [
                            'type' => $mimeType === 'application/pdf' ? 'document' : 'image',
                            'data' => base64_encode($contents),
                            'mime_type' => $mimeType,
                        ],
                        ['type' => 'text', 'text' => self::PROMPT],
                    ],
                    'response_format' => [
                        'type' => 'text',
                        'mime_type' => 'application/json',
                        'schema' => $this->schema(),
                    ],
                ]);
        } catch (Throwable $e) {
            throw new BillReadException('Could not reach the AI service.', previous: $e);
        }

        if (! $response->successful()) {
            Log::warning('Gemini bill read failed', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);

            throw new BillReadException('The AI service returned an error ('.$response->status().').');
        }

        $data = json_decode($this->extractText($response->json()), true);

        if (! is_array($data) || ! is_numeric($data['amount'] ?? null) || (float) $data['amount'] <= 0) {
            throw new BillReadException('Could not find the amount on this bill. Please enter it manually.');
        }

        $str = fn (string $k): ?string => isset($data[$k]) && is_scalar($data[$k]) && $data[$k] !== '' ? (string) $data[$k] : null;

        return [
            'provider' => $str('provider'),
            'type' => in_array($data['type'] ?? null, ['electricity', 'water', 'sewerage'], true) ? (string) $data['type'] : null,
            'account_no' => $str('account_no'),
            'period' => preg_match('/^\d{4}-\d{2}$/', (string) $str('period')) ? $str('period') : null,
            'amount' => round((float) $data['amount'], 2),
            'usage' => is_numeric($data['usage'] ?? null) ? (float) $data['usage'] : null,
            'usage_unit' => $str('usage_unit'),
            'due_date' => $str('due_date'),
        ];
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        $nullableString = ['type' => ['string', 'null']];

        return [
            'type' => 'object',
            'properties' => [
                'provider' => ['type' => ['string', 'null'], 'description' => 'Company name, e.g. TNB, SAMB'],
                'type' => ['type' => ['string', 'null'], 'enum' => ['electricity', 'water', 'sewerage', null]],
                'account_no' => $nullableString,
                'period' => ['type' => ['string', 'null'], 'description' => 'YYYY-MM'],
                'amount' => ['type' => 'number', 'description' => 'Total amount due in RM'],
                'usage' => ['type' => ['number', 'null']],
                'usage_unit' => ['type' => ['string', 'null'], 'description' => 'kWh or m3'],
                'due_date' => ['type' => ['string', 'null'], 'description' => 'YYYY-MM-DD'],
            ],
            'required' => ['amount'],
        ];
    }

    /** Interactions API: text is in steps[-1].content[*].text (fallback: output_text). */
    private function extractText(mixed $json): string
    {
        if (! is_array($json)) {
            return '';
        }

        $steps = $json['steps'] ?? null;
        if (is_array($steps) && $steps !== []) {
            $last = end($steps);
            foreach (is_array($last) && is_array($last['content'] ?? null) ? $last['content'] : [] as $part) {
                if (is_array($part) && is_string($part['text'] ?? null)) {
                    return $part['text'];
                }
            }
        }

        return is_string($json['output_text'] ?? null) ? $json['output_text'] : '';
    }
}
