@extends('layouts.app')

@section('content')
<style>
    /* レポート画面専用スタイル（CSS一体型） */
    .report-container {
        max-width: 1000px;
        margin: 40px auto;
        padding: 0 20px;
    }
    .report-container h1 {
        font-size: 22px;
        font-weight: bold;
        margin-bottom: 8px;
    }
    .report-container .subtitle {
        font-size: 13px;
        color: #666;
        margin-bottom: 30px;
    }
    .report-container h2 {
        font-size: 16px;
        font-weight: bold;
        margin-bottom: 15px;
    }
    .report-container .section-desc {
        font-size: 11px;
        color: #666;
        margin-bottom: 12px;
    }

    /* カードグリッド */
    .report-container .card-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 40px;
    }
    .report-container .card {
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        padding: 20px;
        background: #fff;
    }
    .report-container .card-title {
        font-size: 12px;
        color: #555;
        margin-bottom: 10px;
    }
    .report-container .card-value {
        font-size: 24px;
        font-weight: bold;
        color: #111;
    }

    /* 月次推移テーブル */
    .report-container .table-container {
        margin-bottom: 40px;
    }
    .report-container table {
        width: 100%;
        border-collapse: collapse;
    }
    .report-container th, .report-container td {
        padding: 12px 15px;
        text-align: left;
        font-size: 14px;
        border-bottom: 1px solid #eaeaea;
    }
    .report-container th {
        font-weight: bold;
        color: #333;
    }
    .report-container td {
        color: #111;
    }
</style>

<div class="report-container">
    <h1>マイ勤怠レポート</h1>
    <p class="subtitle">過去 6 ヶ月の勤怠データから集計しています。</p>

    <!-- 基本サマリー -->
    <h2>基本サマリー</h2>
    <div class="card-grid">
        <div class="card">
            <div class="card-title">総労働時間</div>
            <div class="card-value">{{ $summary['total_hours'] }}</div>
        </div>
        <div class="card">
            <div class="card-title">総残業時間</div>
            <div class="card-value">{{ $summary['overtime_hours'] }}</div>
        </div>
        <div class="card">
            <div class="card-title">平均労働時間 / 日</div>
            <div class="card-value">{{ $summary['average_hours'] }}</div>
        </div>
    </div>

    <!-- 月次推移 -->
    <div class="table-container">
        <h2>月次推移 (過去 6 ヶ月)</h2>
        <table>
            <thead>
                <tr>
                    <th>月</th>
                    <th>労働時間</th>
                    <th>残業時間</th>
                </tr>
            </thead>
            <tbody>
                @foreach($monthlyTrends as $trend)
                <tr>
                    <td>{{ $trend['month'] }}</td>
                    <td>{{ $trend['total_hours'] }}</td>
                    <td>{{ $trend['overtime_hours'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- 異常検知 -->
    <div>
        <h2>今月の異常検知</h2>
        <p class="section-desc">基準: 始業 09:00 / 終業 18:00 / 長時間労働は 1 日 10 時間超</p>
        <div class="card-grid">
            <div class="card">
                <div class="card-title">遅刻回数</div>
                <div class="card-value">{{ $anomalies['late_count'] }} 回</div>
            </div>
            <div class="card">
                <div class="card-title">早退回数</div>
                <div class="card-value">{{ $anomalies['early_leave_count'] }} 回</div>
            </div>
            <div class="card">
                <div class="card-title">長時間労働日数</div>
                <div class="card-value">{{ $anomalies['long_work_count'] }} 日</div>
            </div>
        </div>
    </div>
</div>
@endsection