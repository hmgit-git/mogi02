<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreAttendanceEditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 認可はルート/ポリシー側で別途
    }

    public function rules(): array
    {
        return [
            'date'            => ['required', 'date_format:Y-m-d'],
            'clock_in'        => ['nullable', 'date_format:H:i'],
            'clock_out'       => ['nullable', 'date_format:H:i'],
            'breaks'          => ['array'],
            'breaks.*.start'  => ['nullable', 'date_format:H:i'],
            'breaks.*.end'    => ['nullable', 'date_format:H:i'],
            'note'            => ['required', 'string'], // 備考は必須
        ];
    }

    public function messages(): array
    {
        return [
            'note.required'         => '備考を記入してください',
            'clock_in.date_format'  => '出勤時間もしくは退勤時間が不適切な値です',
            'clock_out.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'breaks.*.start.date_format' => '休憩時間が不適切な値です',
            'breaks.*.end.date_format'   => '休憩時間もしくは退勤時間が不適切な値です',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $d   = $this->input('date');
            $in  = $this->input('clock_in');
            $out = $this->input('clock_out');

            $cin  = $in  ? Carbon::createFromFormat('Y-m-d H:i', "{$d} {$in}",  'Asia/Tokyo') : null;
            $cout = $out ? Carbon::createFromFormat('Y-m-d H:i', "{$d} {$out}", 'Asia/Tokyo') : null;

            // 1) 出勤 > 退勤 / 退勤 < 出勤
            if ($cin && $cout && $cin->gt($cout)) {
                $v->errors()->add('clock_in',  '出勤時間もしくは退勤時間が不適切な値です');
                $v->errors()->add('clock_out', '出勤時間もしくは退勤時間が不適切な値です');
            }

            // 2) 休憩の位置関係チェック
            $breaks = collect($this->input('breaks', []));
            foreach ($breaks as $idx => $br) {
                $bs = $br['start'] ?? null;
                $be = $br['end']   ?? null;

                $bsAt = $bs ? Carbon::createFromFormat('Y-m-d H:i', "{$d} {$bs}", 'Asia/Tokyo') : null;
                $beAt = $be ? Carbon::createFromFormat('Y-m-d H:i', "{$d} {$be}", 'Asia/Tokyo') : null;

                // 開始 > 終了
                if ($bsAt && $beAt && $bsAt->gt($beAt)) {
                    $v->errors()->add("breaks.$idx.start", '休憩時間が不適切な値です');
                }

                // 出勤より前 / 退勤より後
                if ($cin && $bsAt && $bsAt->lt($cin)) {
                    $v->errors()->add("breaks.$idx.start", '休憩時間が不適切な値です');
                }
                if ($cout && $bsAt && $bsAt->gt($cout)) {
                    $v->errors()->add("breaks.$idx.start", '休憩時間が不適切な値です');
                }

                // 休憩終了が退勤より後
                if ($cout && $beAt && $beAt->gt($cout)) {
                    $v->errors()->add("breaks.$idx.end", '休憩時間もしくは退勤時間が不適切な値です');
                }
            }
        });
    }
}
