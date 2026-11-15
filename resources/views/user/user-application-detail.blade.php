@extends('layouts.app')

@section('css')
@vite('resources/css/user/user-application-detail.css')
@endsection

@section('content')
<div class="detail__content">
    <div class="detail__header">
        <h1 class="content__header--item">勤怠詳細</h1>
    </div>
    <div class="applied-form">
        <div class="applied-form__content">
            {{-- 名前 --}}
            <div class="applied-form__group">
                <label class="applied-form__header">名前</label>
                <div class="applied-form__input-group">
                    <input class="applied-form__input--name" type="text" name="name" value="{{ $user->name }}" readonly>
                </div>
            </div>

            {{-- 日付（スペーサーを挟んで退勤時間の列と位置を合わせる） --}}
            <div class="applied-form__group">
                <label class="applied-form__header">日付</label>
                <div class="applied-form__input-group">
                    <input class="applied-form__input" type="text" value="{{ $application->new_date->format('Y年') }}" readonly>
                    <div class="applied-form__spacer"></div>
                    <input class="applied-form__input" type="text" value="{{ $application->new_date->format('n月j日') }}" readonly>
                </div>
            </div>

            {{-- 出勤・退勤 --}}
            <div class="applied-form__group">
                <label class="applied-form__header">出勤・退勤</label>
                <div class="applied-form__input-group">
                    <input class="applied-form__input" type="text" value="{{ $application->new_clock_in }}" readonly>
                    <p class="wavy-line">〜</p>
                    <input class="applied-form__input" type="text" value="{{ $application->new_clock_out }}" readonly>
                </div>
            </div>

            {{-- 休憩ループ --}}
            @foreach($application->proposalBreaks as $index => $break)
                <div class="applied-form__group">
                    <label class="applied-form__header">{{ $index === 0 ? '休憩' : '休憩' . ($index + 1) }}</label>
                    <div class="applied-form__input-group">
                        <input class="applied-form__input readonly" type="text" name="new_break_in[]"
                            value="{{ \Carbon\Carbon::parse($break->break_in)->format('H:i') }}" readonly>
                        <p>〜</p>
                        <input class="applied-form__input readonly" type="text" name="new_break_out[]"
                            value="{{ $break->break_out ? \Carbon\Carbon::parse($break->break_out)->format('H:i') : '' }}" readonly>
                    </div>
                </div>
            @endforeach

            {{-- 末尾に空の休憩スロットを1つ表示 --}}
            <div class="applied-form__group">
                <label class="applied-form__header">{{ $application->proposalBreaks->count() === 0 ? '休憩' : '休憩' . ($application->proposalBreaks->count() + 1) }}</label>
                <div class="applied-form__input-group">
                    <input class="applied-form__input readonly" type="text" value="" readonly>
                    <p>〜</p>
                    <input class="applied-form__input readonly" type="text" value="" readonly>
                </div>
            </div>

            {{-- 備考 --}}
            <div class="applied-form__group">
                <label class="applied-form__header">備考</label>
                <div class="applied-form__input-group">
                    <textarea class="applied-form__textarea" name="comment" readonly>{{ $application->comment }}</textarea>
                </div>
            </div>
        </div>

        <div class="applied-form__button">
            @if ($application->approval_status === '承認待ち')
                <p class="applied-form__warning">* 承認待ちのため修正できません。</p>
            @elseif ($application->approval_status === '承認済み')
                <p class="applied-form__item">承認済み</p>
            @endif
        </div>
    </div>
</div>
@endsection