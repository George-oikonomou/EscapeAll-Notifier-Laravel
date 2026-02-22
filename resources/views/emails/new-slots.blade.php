<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #0f0f1a;
            color: #e0e0e0;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 32px 24px;
        }
        .card {
            background: linear-gradient(135deg, rgba(30, 30, 60, 0.95), rgba(20, 20, 45, 0.95));
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 24px;
        }
        .header {
            text-align: center;
            margin-bottom: 24px;
        }
        .header h1 {
            color: #a78bfa;
            font-size: 24px;
            margin: 0 0 8px 0;
        }
        .header p {
            color: #9ca3af;
            font-size: 14px;
            margin: 0;
        }
        .room-title {
            font-size: 20px;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 4px 0;
        }
        .room-company {
            font-size: 14px;
            color: #8b5cf6;
            margin: 0 0 20px 0;
        }
        .date-group {
            margin-bottom: 16px;
        }
        .date-label {
            font-size: 15px;
            font-weight: 600;
            color: #c4b5fd;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid rgba(139, 92, 246, 0.2);
        }
        .time-slots {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .time-badge {
            display: inline-block;
            background: rgba(139, 92, 246, 0.2);
            border: 1px solid rgba(139, 92, 246, 0.4);
            color: #e0e0e0;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
        }
        .summary {
            text-align: center;
            color: #9ca3af;
            font-size: 13px;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid rgba(139, 92, 246, 0.15);
        }
        .footer {
            text-align: center;
            color: #6b7280;
            font-size: 12px;
            padding: 16px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>🎉 Νέες Διαθέσιμες Ώρες!</h1>
                <p>Βρέθηκαν {{ $totalSlots }} νέα slot(s) για το δωμάτιο που παρακολουθείτε</p>
            </div>

            <p class="room-title">{{ $room->title ?? $room->label }}</p>
            <p class="room-company">{{ $room->provider ?? $room->company?->name ?? '' }}</p>

            @foreach ($slotsByDate as $date => $times)
                <div class="date-group">
                    <div class="date-label">
                        📅 {{ \Carbon\Carbon::parse($date)->locale('el')->isoFormat('dddd, D MMMM YYYY') }}
                    </div>
                    <div class="time-slots">
                        @foreach ($times as $time)
                            <span class="time-badge">🕐 {{ $time }}</span>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="summary">
                Αυτό το email στάλθηκε γιατί έχετε ενεργοποιημένη υπενθύμιση για αυτό το δωμάτιο.
            </div>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} EscapeAll Notifier
        </div>
    </div>
</body>
</html>
