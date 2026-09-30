<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Presensi Online | DINAMIT BPS Kabupaten Demak</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>

    <!-- Tailwind CSS with DINAMIT Theme -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        bps: {
                            blue: '#005596',
                            'blue-light': '#0070c5',
                            'blue-dark': '#003e6e',
                            orange: '#F2911B',
                            'orange-light': '#ffa73b',
                            green: '#6CBE45',
                            'green-light': '#82d35c',
                            dark: '#0A2540',
                        }
                    },
                    boxShadow: {
                        'glass': '0 8px 32px 0 rgba(0, 85, 150, 0.08)',
                        'card': '0 4px 20px -2px rgba(15, 23, 42, 0.06), 0 2px 6px -1px rgba(15, 23, 42, 0.04)',
                        'glow': '0 0 20px -3px rgba(0, 85, 150, 0.25)',
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .bg-grid-pattern {
            background-color: #f8fafc;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 24px 24px;
        }

        /* Leaflet custom styling */
        .leaflet-popup-content-wrapper {
            border-radius: 0.75rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            padding: 4px;
        }
        .leaflet-popup-content {
            margin: 10px 14px;
            font-family: 'Inter', sans-serif;
            font-size: 0.875rem;
            line-height: 1.25rem;
        }
    </style>
</head>

<body class="min-h-full bg-grid-pattern text-slate-800 antialiased flex flex-col justify-between">
    {{ $slot }}

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
</body>

</html>

