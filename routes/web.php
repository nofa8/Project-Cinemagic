<?php

// use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });


use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdministrativeController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\ScreeningController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CartController;
use App\Models\Screening;
use App\Models\Customer;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/* ----- PUBLIC ROUTES ----- */

Route::view('/', 'home')->name('home');

Route::get('movies/showcase', [MovieController::class, 'showCase'])
    ->name('movies.showcase')
    ->can('viewShowCase', Movie::class);

Route::get('movies/{movie}/curriculum', [MovieController::class, 'showCurriculum'])
    ->name('movies.curriculum')
    ->can('viewCurriculum', Movie::class);


/* ----- Non-Verified users ----- */
Route::middleware('auth')->group(function () {
    Route::get('/password', [ProfileController::class, 'editPassword'])->name('profile.edit.password');
});

/* ----- Verified users ----- */
Route::middleware('auth', 'verified')->group(function () {



    /* ----- Non-Verified users ----- */
    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });



    Route::view('/dashboard', 'dashboard')->name('dashboard');

    Route::delete('movies/{movie}/image', [MovieController::class, 'destroyImage'])
        ->name('movies.image.destroy')
        ->can('update', Movie::class);

    //Movie resource routes are protected by MoviePolicy on the controller
    // The route 'show' is public (for anonymous user)
    Route::resource('movies', MovieController::class)->except(['show']);

    //Department resource routes are protected by DepartmentPolicy on the controller
    Route::resource('departments', GenreController::class);

    Route::get('screenings/my', [ScreeningController::class, 'myScreenings'])
        ->name('screenings.my')
        ->can('viewMy', Screening::class);

    //Screening resource routes are protected by ScreeningPolicy on the controller
    //Screenings index and show are public
    Route::resource('screenings', ScreeningController::class)->except(['index', 'show']);

    Route::get('customers/my', [CustomerController::class, 'myCustomers'])
        ->name('customers.my')
        ->can('viewMy', Customer::class);

    Route::delete('customers/{Customer}/photo', [CustomerController::class, 'destroyPhoto'])
        ->name('customers.photo.destroy')
        ->can('update', 'Customer');

    //Customer resource routes are protected by CustomerPolicy on the controller
    Route::resource('customers', CustomerController::class);

    Route::get('customers/my', [CustomerController::class, 'myCustomers'])
        ->name('customers.my')
        ->can('viewMy', Customer::class);
    Route::delete('customers/{customer}/photo', [CustomerController::class, 'destroyPhoto'])
        ->name('customers.photo.destroy')
        ->can('update', 'customer');
    //Customer resource routes are protected by CustomerPolicy on the controller
    Route::resource('customers', CustomerController::class);

    Route::delete('administratives/{administrative}/photo', [AdministrativeController::class, 'destroyPhoto'])
        ->name('administratives.photo.destroy')
        ->can('update', 'administrative');

    //Admnistrative resource routes are protected by AdministrativePolicy on the controller
    Route::resource('administratives', AdministrativeController::class);

    // Confirm (store) the cart and save Screenings registration on the database:
    Route::post('cart', [CartController::class, 'confirm'])
        ->name('cart.confirm')
        ->can('confirm-cart');
});

/* ----- OTHER PUBLIC ROUTES ----- */

// Use Cart routes should be accessible to the public */
Route::middleware('can:use-cart')->group(function () {
    // Add a Screening to the cart:
    Route::post('cart/{Screening}', [CartController::class, 'addToCart'])
        ->name('cart.add');
    // Remove a Screening from the cart:
    Route::delete('cart/{Screening}', [CartController::class, 'removeFromCart'])
        ->name('cart.remove');
    // Show the cart:
    Route::get('cart', [CartController::class, 'show'])->name('cart.show');
    // Clear the cart:
    Route::delete('cart', [CartController::class, 'destroy'])->name('cart.destroy');
});


//Movie show is public.
Route::resource('movies', MovieController::class)->only(['show']);

//Screenings index and show are public
Route::resource('screenings', ScreeningController::class)->only(['index', 'show']);

require __DIR__ . '/auth.php';