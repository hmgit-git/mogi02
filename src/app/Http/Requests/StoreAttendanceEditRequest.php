<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use Carbon\Exceptions\InvalidFormatException;

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
            'date'          => ['required', 'date_format:Y-m-d'], // hiddenで送ってる日付（YYYY-MM-DD）

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
            'reason.required'              => '備考を記入してください',
            'clock_in_at.date_format'      => '出勤時間もしくは退勤時間が不適切な値です',
            'clock_out_at.date_format'     => '出勤時間もしくは退勤時間が不適切な値です',
            'breaks.*.start_at.date_format' => '休憩時間が不適切な値です',
            'breaks.*.end_at.date_format'  => '休憩時間が不適切な値です',
        ];
    }

    /**
     * バリデータ後のロジックで、業務的な整合チェックをする
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // まず基本ルールでエラーが出ていたら、業務チェックを実行しない（例外対策）
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $tz   = 'Asia/Tokyo';
            // 念のため date を Y-m-d に正規化（H:i:s が混じっていても安全にする）
            $ymd  = Carbon::parse($this->input('date'), $tz)->format('Y-m-d');

            // "H:i" or "H:i:s" を受けるパーサ
            $toDateTime = function (?string $hi) use ($ymd, $tz) {
                if (!$hi) return null;
                $hi = trim($hi);
                $format = preg_match('/^\d{2}:\d{2}:\d{2}$/', $hi) ? 'Y-m-d H:i:s' : 'Y-m-d H:i';
                try {
                    return Carbon::createFromFormat($format, $ymd . ' ' . $hi, $tz);
                } catch (InvalidFormatException $e) {
                    return null; // フォーマット不一致は基本ルールで拾われるのでここでは握りつぶす
                }
            };

            $cin  = $toDateTime($this->input('clock_in_at'));
            $cout = $toDateTime($this->input('clock_out_at'));

            // 1) 出勤 <= 退勤（テスト要件に合わせた文言で clock_in_at にエラー付与）
            if ($cin && $cout && $cin->gt($cout)) {
                $v->errors()->add('clock_in_at', '出勤時間が不適切な値です');
            }

            // 2) 休憩の範囲と順序
            $breaks = $this->input('breaks', []);
            foreach ($breaks as $i => $b) {
                $bs = $toDateTime(Arr::get($b, 'start_at'));
                $be = $toDateTime(Arr::get($b, 'end_at'));

                // どちらも空ならスキップ
                if (!$bs && !$be) continue;

                // 出勤前に休憩開始はNG
                if ($cin && $bs && $bs->lt($cin)) {
                    $v->errors()->add("breaks.$i.start_at", '休憩時間が不適切な値です');
                }

                // 退勤後に休憩開始はNG
                if ($cout && $bs && $bs->gt($cout)) {
                    $v->errors()->add("breaks.$i.start_at", '休憩時間が不適切な値です');
                }

                // 休憩終了 > 退勤 は NG（要件どおりの文言）
                if ($cout && $be && $be->gt($cout)) {
                    $v->errors()->add("breaks.$i.end_at", '休憩時間もしくは退勤時間が不適切な値です');
                }

                // 開始 > 終了 も NG（同文言でまとめる）
                if ($bs && $be && $bs->gt($be)) {
                    $v->errors()->add("breaks.$i.start_at", '休憩時間もしくは退勤時間が不適切な値です');
                }
            }
        });
    }

    /**
     * コントローラで使いやすい形に整えるヘルパ
     * H:i / H:i:s を Y-m-d H:i:s に合成して返す
     */
    public function mergedDateTime(string $hiOrNull): ?string
    {
        if (!$hiOrNull) return null;
        $tz   = 'Asia/Tokyo';
        $ymd  = Carbon::parse($this->input('date'), $tz)->format('Y-m-d');

        $hi = trim($hiOrNull);
        $format = preg_match('/^\d{2}:\d{2}:\d{2}$/', $hi) ? 'Y-m-d H:i:s' : 'Y-m-d H:i';

        return Carbon::createFromFormat($format, "$ymd $hi", $tz)->toDateTimeString();
    }
}
