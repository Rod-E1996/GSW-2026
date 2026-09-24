<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400"></a></p>

<p align="center">
<a href="https://travis-ci.org/laravel/framework"><img src="https://travis-ci.org/laravel/framework.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# Laravel Base

### Web app developed in Laravel 11.x and PHP 8.2+

#### Introduction
This is a system developed in order to have a template with everything you need to start a new development quickly. It has been successfully upgraded from Laravel 8 to Laravel 11, strictly using Blade without Vite or modern frontend build tools, preserving legacy structures for high compatibility.

# Contains

- public layout
- administrative layout
- Registration Form
- login
- password update and recovery by email
- permissions management (Spatie Permission v6)
- menu access by permissions
- role management
- user management
- user profile
- automated audit
- error logs
- error logs by email and whatsapp messages
- send emails in background
- background process monitoring in real time
- an example crud
- bootstrap 5 and alerts
- unique or multiple session by user
- Session logs and device info 
- Notify new logins by email 
- Close remote sessions

# Steps to install
1.  clone the repository on your device
```bash
git clone https://github.com/alexis691/Laravel-base-project.git
```
2.  run: 
```bash
composer install
```
3. create the .env file, use the .env.example as a guide
4. create an empty DB
5. open project in your preferred code editor and run migrations
```bash
php artisan migrate
```
6. run seeders
```bash
php artisan db:seed	
```
7. open project in your preffered browser as (http://localhost/NAME_PROJECT/public/)

OR 

7. open project in console and run 
```bash
php artisan serve
```
8. Sign up and enjoy!

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
