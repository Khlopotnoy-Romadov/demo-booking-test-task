<?php

use App\Models\Room;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $rooms = Room::query()
        ->with('office')
        ->get()
        ->sortBy(fn (Room $room): string => $room->office->name.'|'.$room->name)
        ->map(fn (Room $room): array => [
            'id' => $room->id,
            'name' => $room->name,
            'capacity' => $room->capacity,
            'office' => $room->office->name,
            'timezone' => $room->office->timezone,
        ])
        ->values();

    return view('demo', ['rooms' => $rooms]);
});

Route::get('/api', function () {
    return response()->json([
        'name' => config('app.name'),
        'description' => 'API бронирования переговорных. Демо-страница: /',
        'authentication' => 'Authorization: Bearer <токен Sanctum>',
        'demo_tokens' => ['anna-token', 'boris-token'],
        'endpoints' => [
            ['method' => 'GET', 'uri' => '/api/bookings', 'description' => 'Мои брони'],
            ['method' => 'POST', 'uri' => '/api/bookings', 'description' => 'Создать бронь'],
            ['method' => 'DELETE', 'uri' => '/api/bookings/{booking}', 'description' => 'Отменить свою бронь'],
            ['method' => 'GET', 'uri' => '/api/rooms/{room}/bookings/today', 'description' => 'Брони комнаты на сегодня'],
        ],
    ]);
});
