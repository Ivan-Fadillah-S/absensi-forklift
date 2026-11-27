<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk - Absensi Forklift</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f4f3ee] text-[#1c1f24]">
<div class="mx-auto min-h-screen max-w-md">

    <header class="bg-[#1c1f24] px-6 pb-8 pt-10 text-white">
        <div class="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-[#f5b800] text-lg font-black text-[#1c1f24]">KI</div>
        <h1 class="text-3xl font-extrabold leading-tight">Absensi &amp; Timesheet<br>Operator Forklift</h1>
        <p class="mt-2 text-sm text-gray-300">PT. Karenu Indah · Site Cikarang</p>
    </header>

    <main class="px-6 py-8">
        <h2 class="mb-5 text-xl font-bold">Masuk ke akun</h2>

        <form method="POST" action="{{ route('login.attempt') }}" class="space-y-5">
            @csrf

            <div>
                <label for="username" class="mb-1 block text-sm font-semibold">ID pengguna</label>
                <input id="username" name="username" type="text" value="{{ old('username') }}"
                       required autofocus autocomplete="username" autocapitalize="none"
                       class="w-full rounded-xl border border-stone-300 bg-white px-4 py-3 text-base">
                @error('username')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-semibold">Kata sandi</label>
                <div class="flex overflow-hidden rounded-xl border border-stone-300 bg-white">
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                           class="w-full px-4 py-3 text-base outline-none">
                    <button type="button" id="toggle"
                            class="border-l border-stone-300 bg-stone-50 px-4 text-sm font-semibold">Tampilkan</button>
                </div>
                @error('password')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-start gap-3 rounded-xl border border-stone-300 bg-white p-4 text-sm">
                <input id="consent" name="consent" type="checkbox" value="1" class="mt-1 h-5 w-5">
                <span>Saya setuju lokasi dan foto wajah saya dicatat untuk keperluan absensi. Data hanya dilihat pihak yang berwenang.</span>
            </label>
            @error('consent')
                <p class="text-sm text-red-700">{{ $message }}</p>
            @enderror

            <button id="submit" type="submit" disabled
                    class="w-full rounded-xl bg-[#f5b800] py-3 text-lg font-bold text-[#1c1f24] disabled:bg-stone-200 disabled:text-stone-500">
                Masuk
            </button>
            <p class="text-center text-sm text-stone-500">Centang persetujuan di atas untuk mengaktifkan tombol Masuk.</p>
        </form>

        <p class="mt-6 text-center text-sm text-stone-500">Lupa kata sandi? Hubungi monitoring.</p>
    </main>
</div>

<script>
    const consent = document.getElementById('consent');
    const submitBtn = document.getElementById('submit');
    const pwd = document.getElementById('password');
    const toggle = document.getElementById('toggle');

    consent.addEventListener('change', () => { submitBtn.disabled = !consent.checked; });

    toggle.addEventListener('click', () => {
        const tersembunyi = pwd.type === 'password';
        pwd.type = tersembunyi ? 'text' : 'password';
        toggle.textContent = tersembunyi ? 'Sembunyikan' : 'Tampilkan';
    });
</script>
</body>
</html>