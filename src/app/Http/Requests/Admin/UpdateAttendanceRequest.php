<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Carbon;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 管理者ガードでログインしていればOK（必要に応じて厳格に）
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            // H:i 形式のみ（空は許可）
            'clock_in_at'            => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
            'clock_out_at'           => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],

            'breaks'                 => ['array'],
            'breaks.*.start_at'      => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
            'breaks.*.end_at'        => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],

            // 管理者更新は備考必須
            'reason'                 => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'clock_in_at.regex'           => '時刻は HH:MM 形式で入力してください',
            'clock_out_at.regex'          => '時刻は HH:MM 形式で入力してください',
            'breaks.*.start_at.regex'     => '時刻は HH:MM 形式で入力してください',
            'breaks.*.end_at.regex'       => '時刻は HH:MM 形式で入力してください',
            'reason.required'             => '備考を記入してください',
        ];
    }

    /**
     * 余計な空行（start/end とも空）を除去して正規化
     */
    protected function prepareForValidation(): void
    {
        $breaks = collect($this->input('breaks', []))
            ->map(function ($row) {
                return [
                    'start_at' => $row['start_at'] ?? null,
                    'end_at'   => $row['end_at']   ?? null,
                ];
            })
            ->filter(fn($b) => ($b['start_at'] ?? '') !== '' || ($b['end_at'] ?? '') !== '')
            ->values()
            ->all();

        $this->merge(['breaks' => $breaks]);
    }

    /**
     * 論理チェック（要件通りの日本語メッセージ）
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $att = $this->route('id')
                ? \App\Models\Attendance::with('breaks')->find($this->route('id'))
                : null;

            if (!$att) return; // 念のため

            $tz = 'Asia/Tokyo';
            $workDate = $att->work_date->toDateString();

            $toDateTime = function (?string $hm) use ($workDate, $tz) {
                if (!$hm) return null;
                return Carbon::createFromFormat('Y-m-d H:i', $workDate . ' ' . $hm, $tz);
            };

            $clockIn  = $toDateTime($this->input('clock_in_at'));
            $clockOut = $toDateTime($this->input('clock_out_at'));

            // 1) 出退勤の前後
            if ($clockIn && $clockOut && $clockOut->lt($clockIn)) {
                $v->errors()->add('_top', '出勤時間もしくは退勤時間が不適切な値です');
            }

            // 2) 休憩チェック
            foreach ($this->input('breaks', []) as $row) {
                $s = $toDateTime($row['start_at'] ?? null);
                $e = $toDateTime($row['end_at']   ?? null);

                // 休憩開始が出勤より前／退勤より後
                if ($s && $clockIn && $s->lt($clockIn)) {
                    $v->errors()->add('_top', '休憩時間が不適切な値です');
                    break;
                }
                if ($s && $clockOut && $s->gt($clockOut)) {
                    $v->errors()->add('_top', '休憩時間が不適切な値です');
                    break;
                }

                // 休憩終了が退勤より後
                if ($e && $clockOut && $e->gt($clockOut)) {
                    $v->errors()->add('_top', '休憩時間もしくは退勤時間が不適切な値です');
                    break;
                }

                // 休憩の前後
                if ($s && $e && $e->lt($s)) {
                    $v->errors()->add('_top', '休憩時間が不適切な値です');
                    break;
                }
            }
        });
    }
}
