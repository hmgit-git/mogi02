<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;

class StoreAttendanceEditRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 自分の勤怠に対する申請のみ許可（controller側のクエリで担保してるなら true でもOK）
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'attendance_id' => ['required', 'exists:attendances,id'],
            'date'          => ['required', 'date_format:Y-m-d'], // hiddenで送ってる日付

            // H:i（空OK）を受ける。後で date と合成して比較する
            'clock_in_at'   => ['nullable', 'date_format:H:i'],
            'clock_out_at'  => ['nullable', 'date_format:H:i'],

            'breaks'                => ['array'],
            'breaks.*.start_at'     => ['nullable', 'date_format:H:i'],
            'breaks.*.end_at'       => ['nullable', 'date_format:H:i'],

            'reason'        => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => '備考を記入してください',
            'clock_in_at.date_format'  => '出勤時間もしくは退勤時間が不適切な値です',
            'clock_out_at.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'breaks.*.start_at.date_format' => '休憩時間が不適切な値です',
            'breaks.*.end_at.date_format'   => '休憩時間が不適切な値です',
        ];
    }

    /**
     * バリデータ後のロジックで、業務的な整合チェックをする
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $tz   = 'Asia/Tokyo';
            $date = $this->input('date'); // YYYY-MM-DD

            // H:i → Carbon（null許容）
            $toDateTime = function (?string $hi) use ($date, $tz) {
                if (!$hi) return null;
                return Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $hi, $tz);
            };

            $cin  = $toDateTime($this->input('clock_in_at'));
            $cout = $toDateTime($this->input('clock_out_at'));

            // 1) 出勤 <= 退勤
            if ($cin && $cout && $cin->gt($cout)) {
                $v->errors()->add('clock_in_out', '出勤時間もしくは退勤時間が不適切な値です');
            }

            // 2) 休憩の範囲と順序
            $breaks = $this->input('breaks', []);
            foreach ($breaks as $i => $b) {
                $bs = $toDateTime(Arr::get($b, 'start_at'));
                $be = $toDateTime(Arr::get($b, 'end_at'));

                // 休憩のどちらか片方だけあってもOK（申請で片方ミス修正したいケース）
                if (!$bs && !$be) continue;

                // 出勤・退勤の前後チェック（両方そろっている場合のみ厳密チェック）
                if ($cin && $bs && $bs->lt($cin)) {
                    $v->errors()->add("breaks.$i.start_at", '休憩時間が不適切な値です');
                }
                if ($cout && $be && $be->gt($cout)) {
                    // 3) 休憩終了が退勤後
                    $v->errors()->add("breaks.$i.end_at", '休憩時間もしくは退勤時間が不適切な値です');
                }

                // 開始 <= 終了（両方ある時）
                if ($bs && $be && $bs->gt($be)) {
                    $v->errors()->add("breaks.$i.start_at", '休憩時間もしくは退勤時間が不適切な値です');
                }

                // 出勤/退勤どちらかが空のときは、ビジネスとして許すなら上記で終了。
                // 厳密に「出勤/退勤が必須」としたい場合はここで追加チェック。
            }
        });
    }

    /**
     * コントローラで使いやすい形に整えるヘルパ
     * H:i を Y-m-d H:i に合成した文字列を返す
     */
    public function mergedDateTime(string $hiOrNull): ?string
    {
        if (!$hiOrNull) return null;
        $tz   = 'Asia/Tokyo';
        $date = $this->input('date');
        return Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $hiOrNull, $tz)->toDateTimeString();
    }
}
