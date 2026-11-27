<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# Absensi dan Timesheet Operator Forklift

Proyek pengabdian masyarakat, Universitas Gunadarma.
Mitra: PT Karenu Indah (Site Cikarang). Pengganti aplikasi absensi Hadirr.

## Fitur
- Operator: absen masuk dan pulang (GPS dan foto wajah), timesheet, riwayat absensi, keterangan cuti/izin/sakit.
- Monitoring: dashboard harian, koreksi absen beserta jejak perubahan, rekap periode (26 sampai 25) dengan unduh Excel, data operator, pengaturan area absen.
- Asisten AI: deteksi anomali absen (aturan dan model statistik, tanpa internet) dan asisten koreksi bahasa natural (Gemini, opsional).

## Teknologi
Laravel 13, MySQL, Tailwind CSS, PhpSpreadsheet.

## Kebutuhan
- PHP 8.3 atau lebih baru, dengan ekstensi: curl, fileinfo, mbstring, openssl, pdo_mysql, zip, gd, xml, dom
- Composer 2
- Node.js versi LTS terbaru
- MySQL 8 (misalnya lewat Laragon)

## Cara menjalankan
1. Clone repositori: `git clone https://github.com/USERNAME/absensi-forklift.git`, lalu `cd absensi-forklift`
2. Pasang paket PHP: `composer install`
3. Salin pengaturan: `copy .env.example .env` (Windows), lalu `php artisan key:generate`
4. Buat database kosong bernama `absensi_forklift` (utf8mb4_unicode_ci). Bagian DB_ di `.env` sudah sesuai Laragon.
5. Buat tabel dan data contoh: `php artisan migrate:fresh --seed`
6. Data contoh absen: `php artisan db:seed --class=DemoAttendanceSeeder` dan `php artisan db:seed --class=DemoAnomaliSeeder`
7. Tautan foto: `php artisan storage:link`
8. Pasang paket tampilan: `npm install`, lalu `npm run build` (atau `npm run dev` saat mengembangkan)
9. Jalankan: `php artisan serve`, lalu buka http://127.0.0.1:8000

Catatan Windows: bila `npm` diblokir di PowerShell, pakai `npm.cmd`.

## Akun contoh (hanya untuk pengembangan)
- Monitoring: `anwar`
- Operator: `operator01` sampai `operator15`
- Kata sandi semuanya: `password`

## Fitur AI (opsional)
Isi `GEMINI_API_KEY` di `.env` dengan key pribadi dari Google AI Studio (paket gratis, jangan aktifkan billing).
`GEMINI_MODEL` boleh berisi beberapa nama model dipisah koma. Sistem mencoba model berikutnya bila satu model sibuk.
Tanpa key, deteksi anomali tetap berjalan; hanya asisten koreksi yang nonaktif.

## Aturan kerja tim
- Jangan pernah commit file `.env` atau API key.
- Sebelum mulai bekerja: `git pull`.
- Satu fitur satu branch: `git checkout -b fitur/nama-fitur`, lalu buat Pull Request ke `main`.
- Jangan mengubah file migrasi yang sudah ada. Buat migrasi baru dengan `php artisan make:migration`.
- Selesai `git pull`, jalankan `composer install`, `php artisan migrate`, dan `npm install` bila ada perubahan.