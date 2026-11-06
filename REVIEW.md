# Ревью API бронирования переговорных

Объект ревью: `app/Http/Controllers/BookingController.php`, связанные модели, миграции, маршруты и конфигурация.
Стек: PHP 8.4, Laravel 13.39, Sanctum 4.3, Carbon 3.15, PostgreSQL 17.

Легенда серьёзности:

- **critical** — ломает основное правило задачи, либо это уязвимость/потеря данных. Чинить до всего остального.
- **major** — неверное поведение или обязательное требование ТЗ не выполнено, но без прямой уязвимости.
- **minor** — качество, устойчивость, контракт API, поддерживаемость.

Метод: статический анализ кода и конфигурации (в текущем окружении нет Docker, поэтому запросы не выполнялись). Где вывод зависит от поведения фреймворка или PostgreSQL, это отмечено явно.

## Сводка

| # | Серьёзность | Проблема | Где |
|---|---|---|---|
| 1 | critical | SQL-инъекция в `store` | [`BookingController.php:23-26`](app/Http/Controllers/BookingController.php#L23-L26) |
| 2 | critical | Автор брони берётся из запроса — подмена `user_id` | [`BookingController.php:37`](app/Http/Controllers/BookingController.php#L37) |
| 3 | critical | `destroy` не проверяет авторство — удаление чужой брони | [`BookingController.php:45-50`](app/Http/Controllers/BookingController.php#L45-L50) |
| 4 | critical | Встречи «впритык» считаются пересечением (`<=` / `>=`) | [`BookingController.php:25-26`](app/Http/Controllers/BookingController.php#L25-L26) |
| 5 | major | Часовой пояс офиса не учитывается при проверке рабочих часов | [`BookingController.php:32-35`](app/Http/Controllers/BookingController.php#L32-L35) |
| 6 | major | Проверяется только начало, а не весь интервал | [`BookingController.php:33`](app/Http/Controllers/BookingController.php#L33) |
| 7 | major | Нет проверки длительности и шага 15 минут | [`BookingController.php:21-43`](app/Http/Controllers/BookingController.php#L21-L43) |
| 8 | major | Нет валидации входа → мусор и `null` дают 500 вместо 422 | [`BookingController.php:21-43`](app/Http/Controllers/BookingController.php#L21-L43) |
| 9 | major | Конфликт отдаётся как 409 и в самодельном формате, а не 422 | [`BookingController.php:28-30`](app/Http/Controllers/BookingController.php#L28-L30) |
| 10 | major | Порядок операций: SQL-запрос раньше разбора и проверки дат | [`BookingController.php:23-37`](app/Http/Controllers/BookingController.php#L23-L37) |
| 11 | major | Несуществующий `room_id` → нарушение FK → 500 вместо 422 | [`BookingController.php:37`](app/Http/Controllers/BookingController.php#L37) |
| 12 | major | Тип колонок `timestamp without time zone` + нет явной нормализации времени | [`2026_10_29_000003_create_bookings_table.php:15-16`](database/migrations/2026_10_29_000003_create_bookings_table.php#L15-L16) |
| 13 | major | Нет индекса под запрос конфликтов (seq scan) | [`2026_10_29_000003_create_bookings_table.php`](database/migrations/2026_10_29_000003_create_bookings_table.php) |
| 14 | major | `today()` считает «сегодня» в UTC, а не в поясе офиса | [`BookingController.php:55`](app/Http/Controllers/BookingController.php#L55) |
| 15 | minor | N+1 в `today()` и формат времени через `__toString` | [`BookingController.php:58-59`](app/Http/Controllers/BookingController.php#L58-L59) |
| 16 | minor | `Log::withContext` после создания, контекст «протекает», не тот логгер | [`BookingController.php:39-40`](app/Http/Controllers/BookingController.php#L39-L40) |
| 17 | minor | Разнобой в контракте: сырые модели, 409/422/204, нет ресурсов | [`BookingController.php`](app/Http/Controllers/BookingController.php) |
| 18 | minor | `destroy` берёт `{id}` без route-model-binding | [`routes/api.php:9`](routes/api.php#L9) |
| 19 | minor | Нет rate limiting на API | [`bootstrap/app.php`](bootstrap/app.php) |
| 20 | minor | `offices.timezone` не валидируется как IANA-зона | [`2026_10_29_000001_create_offices_table.php:14`](database/migrations/2026_10_29_000001_create_offices_table.php#L14) |
| 21 | minor | `title` без ограничения длины → 500 на длинной строке | [`2026_10_29_000003_create_bookings_table.php:17`](database/migrations/2026_10_29_000003_create_bookings_table.php#L17) |
| 22 | minor | Вся логика в контроллере, нет FormRequest/Policy/сервиса | [`app/`](app) |
| 23 | minor | Тестами покрыт только `index` | [`tests/Feature`](tests/Feature) |

---

## Critical

### 1. SQL-инъекция в `store`

[`BookingController.php:23-26`](app/Http/Controllers/BookingController.php#L23-L26)

```php
$conflict = DB::select("SELECT id FROM bookings
    WHERE room_id = {$request->room_id}
    AND starts_at <= '{$request->ends_at}'
    AND ends_at >= '{$request->starts_at}'");
```

Значения из запроса подставляются в SQL строкой, без биндингов. Это не «теоретическая» инъекция: `room_id` — целое, даты — строки, всё приходит от клиента.

Как воспроизвести (ожидаемое поведение по коду):

- `{"room_id": "0 OR 1=1"}` — условие становится всегда истинным, любой запрос получает 409, создать бронь невозможно.
- `{"starts_at": "2026-11-02T10:00:00+03:00'); ... --"}` — `DB::select` без биндингов в pgsql-драйвере PDO уходит одним `PQexec`, который допускает несколько стейтментов в строке. То есть это потенциально не только чтение, но и запись/DDL.

Как исправить: только query builder / Eloquent с биндингами.

```php
$conflict = Booking::query()
    ->where('room_id', $room->id)
    ->where('starts_at', '<', $endsAt)
    ->where('ends_at', '>', $startsAt)
    ->exists();
```

Отдельно: `DB::select` возвращает «сырые» строки, а не модели, из-за чего логика не переиспользует ни касты, ни скоупы.

### 2. Автор брони берётся из запроса

[`BookingController.php:37`](app/Http/Controllers/BookingController.php#L37)

```php
$booking = Booking::create($request->all());
```

В `Booking` поле `user_id` указано в атрибуте `#[Fillable(...)]` ([`Booking.php:11`](app/Models/Booking.php#L11)), поэтому `user_id` из тела запроса попадает в модель. Клиент создаёт бронь **от имени другого пользователя**: чужая бронь всплывает в его `GET /api/bookings`, а авторство в `today()` указывает на подставленное лицо.

Это же место — пример «mass assignment из запроса»: любое новое поле, добавленное в `Fillable`, автоматически станет управляемым снаружи.

Как исправить: брать автора из аутентификации и передавать поля явно.

```php
$booking = $request->user()->bookings()->create([
    'room_id' => $room->id,
    'starts_at' => $startsAt,
    'ends_at' => $endsAt,
    'title' => $validated['title'],
]);
```

Плюс убрать `user_id` из `Fillable` (или оставить, но никогда не заполнять из запроса) и добавить тест: «бронь создаётся от текущего пользователя, `user_id` в теле игнорируется».

### 3. `destroy` не проверяет авторство

[`BookingController.php:45-50`](app/Http/Controllers/BookingController.php#L45-L50)

```php
Booking::findOrFail($id)->delete();
```

Любой аутентифицированный пользователь удаляет любую бронь по id. Требование ТЗ «отменить бронь может только её автор» не выполнено. Фактически, пока живы демо-токены из сидера (`anna-token`, `boris-token`), это ещё и мгновенная порча данных.

Как исправить: `BookingPolicy` (`delete`) или явная проверка.

```php
$booking = Booking::findOrFail($id);

abort_unless($booking->user_id === $request->user()->id, 403);

$booking->delete();
```

В Laravel-стиле — `$this->authorize('delete', $booking)` в контроллере и политика, зарегистрированная автоматически по имени модели. Тесты: автор удаляет → 204; чужой → 403 (и бронь остаётся); несуществующий id → 404.

### 4. Встречи «впритык» считаются пересечением

[`BookingController.php:25-26`](app/Http/Controllers/BookingController.php#L25-L26)

```sql
AND starts_at <= '{ends_at}'
AND ends_at   >= '{starts_at}'
```

Правило задачи: 10:00–11:00 и 11:00–12:00 — не пересечение. Нестрогие `<=` / `>=` считают их конфликтом, то есть запрещают самый частый сценарий (подряд идущие встречи). Это ломает основное правило задачи на повседневном кейсе, поэтому critical, а не minor.

Как исправить: строгие неравенства — два интервала `[start, end)` пересекаются, если `a.start < b.end AND a.end > b.start`.

```php
->where('starts_at', '<', $endsAt)
->where('ends_at', '>', $startsAt)
```

Тесты-границы: `10:00–11:00` + `11:00–12:00` → 201; `10:00–11:00` + `10:59:59` → 422; `10:00–11:00` + `10:15–10:45` (внутри) → 422; одинаковые интервалы → 422; разные комнаты в одно время → 201.

---

## Major

### 5. Часовой пояс офиса игнорируется

[`BookingController.php:32-35`](app/Http/Controllers/BookingController.php#L32-L35)

```php
$start = Carbon::parse($request->starts_at);
if ($start->hour < 9 || $start->hour >= 20) { ... }
```

`->hour` — это час в том поясе, который принесла строка запроса, а не в поясе офиса. Клиент может прислать `2026-11-02T10:00:00+00:00` для комнаты в Новосибирске (там это 17:00) — проверка пройдёт, хотя локально это рабочее время; и наоборот, `2026-11-02T23:00:00+07:00` (19:00 в Новосибирске) будет отвергнуто как «нерабочее». Требование «бронь укладывается в рабочее время по местному времени офиса» не выполнено вообще.

Дополнительно: `config('app.clock')`-подобной логики нет, `config/app.php` жёстко задаёт `'timezone' => 'UTC'`, поэтому при отсутствии смещения в строке `Carbon::parse` трактует время как UTC — молча.

Модели для этого уже готовы: `Booking → room → office->timezone`.

Как исправить:

```php
$office = $room->office; // гарантировать загрузку связи

$startsAt = CarbonImmutable::parse($validated['starts_at'])->setTimezone($office->timezone);
$endsAt   = CarbonImmutable::parse($validated['ends_at'])->setTimezone($office->timezone);

$workStart = $startsAt->setTime(9, 0);
$workEnd   = $startsAt->setTime(20, 0);

if ($startsAt->lt($workStart) || $endsAt->gt($workEnd)) {
    // 422
}
```

Важно: `setTime(20, 0)` на дате начала корректно, только если бронь не переходит через сутки; после проверки длительности (≤ 4 ч) это гарантировано, но порядок проверок должен быть именно такой.

### 6. Проверяется только начало интервала

[`BookingController.php:33`](app/Http/Controllers/BookingController.php#L33)

Проверка смотрит только `starts_at`. Бронь 19:30–21:00 или 19:00–23:00 проходит валидацию, хотя выходит за 20:00. Проверять нужно оба конца: `starts_at >= 09:00` **и** `ends_at <= 20:00` локального времени.

### 7. Нет проверки длительности и шага

Нигде не проверяется:

- минимальная длительность 15 минут;
- максимальная 4 часа;
- шаг 15 минут (`10:00`, `10:15`, …) для начала (и, следствие, конца);
- `starts_at < ends_at` — нулевая и отрицательная длительность проходят все проверки (при отрицательной конфликтный запрос вообще теряет смысл).

Как исправить (после приведения к поясу офиса):

```php
$minutes = (int) $startsAt->diffInMinutes($endsAt);

if ($minutes < 15 || $minutes > 240) {
    // 422: длительность от 15 минут до 4 часов
}

if ($startsAt->minute % 15 !== 0 || $startsAt->second !== 0) {
    // 422: время должно быть кратно 15 минутам
}
```

Шаг для `ends_at` не нужен отдельно: он вытекает из кратности начала и кратности длительности, но проще проверить оба — тест на `10:00–10:20` должен падать.

### 8. Нет валидации входа

В методе нет ни `$request->validate()`, ни FormRequest. Последствия:

- `Carbon::parse()` на мусоре бросает `InvalidFormatException` → 500. `"starts_at": "завтра"` или `""` — 500, а не 422.
- `"starts_at": null` — в PHP 8 это `null`, в SQL-строку подставится пустая строка → ошибка синтаксиса/SQLSTATE → 500.
- `room_id` не проверяется через `exists:rooms,id`.
- `title` не обязателен и не ограничен (см. п. 21).
- `ends_at` может быть раньше `starts_at`.

Требование ТЗ «понятные ошибки валидации (422)» не выполнено.

Как исправить: `app/Http/Requests/StoreBookingRequest.php` с правилами `required`, `date` (или `date_format` для ISO 8601), `after:starts_at`, `exists:rooms,id`, `string|max:255`, и кастомными правилами на рабочие часы / шаг / длительность. Ошибки по `ends_at` возвращать как валидационные — тогда формат ответа единый: `{"message": ..., "errors": {...}}` со статусом 422.

### 9. Конфликт отдаётся как 409 в самодельном формате

[`BookingController.php:28-30`](app/Http/Controllers/BookingController.php#L28-L30)

```php
return response()->json(['error' => 'Комната занята'], 409);
```

По ТЗ пересечение — это нарушение правил бронирования, то есть ошибка валидации: 422 и тот же формат, что у остальных ошибок (`message` + `errors`). Сейчас клиент получает два разных контракта ошибок (409 `{error}` и 422 `{error}`), а Laravel-овский `ValidationException` вообще не используется.

Как исправить:

```php
throw ValidationException::withMessages([
    'starts_at' => 'Комната занята в это время.',
]);
```

### 10. Порядок операций в `store` перевёрнут

[`BookingController.php:23-37`](app/Http/Controllers/BookingController.php#L23-L37)

Сначала выполняется SQL по сырым строкам из запроса, и только потом даты парсятся в `Carbon`. Правильный порядок:

1. валидировать структуру (типы, обязательность, `exists`);
2. распарсить в `CarbonImmutable` и привести к поясу офиса;
3. проверить правила (рабочие часы, длительность, шаг);
4. нормализовать в UTC;
5. проверить пересечение;
6. создать запись.

Текущий порядок — это причина, по которой пп. 1, 8 и 11 существуют одновременно: валидация физически не может произойти раньше инъекции.

### 11. Несуществующий `room_id` даёт 500

[`BookingController.php:37`](app/Http/Controllers/BookingController.php#L37)

`room_id` не проверяется. Вставка с несуществующим id нарушает `rooms_id_foreign` → `QueryException` → 500. То же с `title` длиннее 255 символов (см. п. 21). Ожидаемо — 422 с понятным сообщением.

Заодно: для поиска комнаты нужен `Room::with('office')->findOrFail($roomId)`, иначе пояс офиса придёт отдельным запросом.

### 12. `timestamp without time zone` и отсутствие явной нормализации

[`2026_10_29_000003_create_bookings_table.php:15-16`](database/migrations/2026_10_29_000003_create_bookings_table.php#L15-L16)

`$table->timestamp('starts_at')` в PostgreSQL-грамматике Laravel компилируется в `timestamp(0) without time zone` (проверено по `PostgresGrammar::typeTimestamp`). Следствия:

- смещение из ISO 8601 не сохраняется, при записи остаётся только wall-clock;
- секунды обрезаются (`precision = 0`) — для шага 15 минут неважно, но это неявное поведение;
- привязки `DateTimeInterface` форматируются как `Y-m-d H:i:s`, поэтому в БД оказывается wall-clock того пояса, в котором находится распарсенный Carbon. Код нигде не приводит его к UTC или к поясу офиса перед записью — нормализация держится на неявном инварианте «всё в UTC», который обеспечивается только значением `config('app.timezone')`.

Это хрупко: если кто-то поменяет `app.timezone` на `Europe/Moscow` (естественное желание при жалобах на часовые пояса), уже записанные данные и новые записи начнут расходиться, а `today()` и сравнения продолжат считать их UTC.

Как исправить:

- колонки → `$table->timestampTz('starts_at')` / `timestampTz('ends_at')` (`timestamptz`), миграция с `->change()`;
- перед сохранением явно нормализовать: `$startsAt->utc()`, `$endsAt->utc()`;
- отдавать клиенту время в UTC (ISO 8601 с `Z`) или в поясе офиса — но осознанно и одинаково.

Дополнительный бонус `timestamptz`: он нужен для exclusion constraint из бонусной части (см. раздел «Гонка»).

### 13. Нет индекса под запрос конфликтов

[`2026_10_29_000003_create_bookings_table.php`](database/migrations/2026_10_29_000003_create_bookings_table.php)

Проверка пересечения фильтрует по `room_id` и диапазону дат, а индексов в таблице нет вообще — только первичный ключ и два FK (PostgreSQL не создаёт индексы на FK автоматически). На росте данных это seq scan на каждый `store` и `today`.

Как исправить: составной индекс под фактический запрос.

```php
$table->index(['room_id', 'starts_at']);
```

Для проверки «есть ли пересечение» в PostgreSQL уместнее `gist` по `tstzrange` (см. «Гонка»), но составной btree-индекс — минимально достаточный шаг.

### 14. `today()` считает «сегодня» в UTC

[`BookingController.php:55`](app/Http/Controllers/BookingController.php#L55)

```php
->whereDate('starts_at', today())
```

`today()` — это полночь в UTC (`config/app.php`), а «сегодня» в задаче — сутки по местному времени офиса комнаты. В Новосибирске (UTC+7) с 00:00 до 07:00 местного времени метод отдаёт данные за предыдущие сутки; брони, созданные вечером по местному, могут не попасть в выдачу.

Метод править в рамках части 2 не требуется, но в ревью зафиксировать нужно. Правильно: вычислить границы суток в поясе офиса и сравнивать по полуинтервалу.

```php
$office = $room->office;
$dayStart = CarbonImmutable::now($office->timezone)->startOfDay()->utc();
$dayEnd = $dayStart->addDay();

$room->bookings()->where('starts_at', '>=', $dayStart)
    ->where('starts_at', '<', $dayEnd);
```

Такой вариант ещё и позволяет использовать индекс по `starts_at`, в отличие от `whereDate()` (функция над колонкой).

---

## Minor

### 15. N+1 и формат времени в `today()`

[`BookingController.php:58-59`](app/Http/Controllers/BookingController.php#L58-L59)

```php
->map(fn ($b) => [
    'time' => $b->starts_at . ' – ' . $b->ends_at,
    'author' => $b->user->name,
]);
```

- `$b->user->name` в цикле — по запросу на каждую бронь. Нужен `->with('user')` (или `with('user:id,name')`).
- `$b->starts_at . ' – ' . $b->ends_at` — неявный `__toString` у Carbon, формат `Y-m-d H:i:s` в UTC. Клиент ожидает «10:00 – 11:00» по местному времени офиса; формат зависит от внутренней реализации Carbon и не задан явно. Нужен `format('H:i')` (в поясе офиса) либо `toIso8601String()`.
- Возвращается «сырой» массив вместо ресурса — контракт не описан.

### 16. `Log::withContext` — не то место и не тот логгер

[`BookingController.php:39-40`](app/Http/Controllers/BookingController.php#L39-L40)

```php
Log::withContext(['booking_id' => $booking->id]);
Log::channel('bookings')->info('Booking created');
```

- Контекст ставится **после** `create()`. Если запись упадёт (а с текущим кодом это легко), в логе не будет ни `booking_id`, ни сообщения о неудаче.
- `withContext` пишет контекст в логгер канала по умолчанию (`stack → single`), а `channel('bookings')` — это отдельный инстанс логгера. Ожидаемого `booking_id` в `storage/logs/bookings.log`, скорее всего, не будет вовсе.
- Контекст остаётся на логгере до конца процесса: под `php artisan serve` это один запрос, но под Octane или в queued-джобе `booking_id` «протечёт» в последующие записи.

Как исправить: писать с контекстом напрямую и в нужный канал.

```php
Log::channel('bookings')->info('Booking created', [
    'booking_id' => $booking->id,
    'room_id' => $booking->room_id,
    'user_id' => $booking->user_id,
]);
```

### 17. Непоследовательный контракт API

Сейчас: `store` возвращает голую модель со статусом 200 (не 201), `index` — пагинатор, `destroy` — 204, ошибки — `{error}` с 409 или 422. Ни одного API Resource в проекте нет, версии API (`/api/v1`) нет.

Это не блокер, но конкретно для задачи важно: ошибки валидации должны иметь один предсказуемый формат, иначе «понятные ошибки 422» превращаются в лотерею. Как минимум — использовать `ValidationException` (см. п. 9). Как максимум — `BookingResource` + `201` с `Location` для `store`.

Замечание к тестам: существующий `BookingIndexTest` завязан на `data.*` от пагинатора, поэтому менять форму `index` без правки теста нельзя.

### 18. `destroy` без route-model-binding

[`routes/api.php:9`](routes/api.php#L9)

`Route::delete('/bookings/{id}', ...)` и `destroy($id)` — вручную. Рядом `today(Room $room)` использует биндинг, то есть стиль уже разъехался. Биндинг дал бы 404 автоматически, читаемее и позволил бы политику (`$this->authorize('delete', $booking)`) без дополнительного `findOrFail`.

### 19. Нет rate limiting на API

[`bootstrap/app.php`](bootstrap/app.php) не включает `throttleApi()`, у маршрутов нет `throttle`. В современном Laravel ограничение по умолчанию для `api`-группы не включено. Итог: неограниченный перебор `Authorization: Bearer` и полное отсутствие защиты от случайного «шторма» запросов. Для демо-задания с фиксированными токенами это критично меньше, но в ревью отметить стоит: `->withMiddleware(fn (Middleware $middleware) => $middleware->throttleApi())`.

### 20. `offices.timezone` не валидируется

[`2026_10_29_000001_create_offices_table.php:14`](database/migrations/2026_10_29_000001_create_offices_table.php#L14)

Обычная строка. Если там окажется опечатка (`Europe/Moskow`) или пустое значение, `Carbon::setTimezone()`/`now($tz)` бросит исключение — и это будет 500 на любом запросе к такой комнате. В сидере значения корректные, поэтому пока не проявляется, но контракт «это IANA-зона» нигде не зафиксирован. Варианты: проверять при записи, либо фолбэк на `config('app.timezone')` с логированием.

### 21. `title` без ограничения

[`2026_10_29_000003_create_bookings_table.php:17`](database/migrations/2026_10_29_000003_create_bookings_table.php#L17)

`string('title')` → `varchar(255)`. Строка длиннее 255 символов — `QueryException` → 500. Нужна валидация `max:255` (и, вероятно, `min:1`).

### 22. Архитектура: всё в контроллере

Не проблема сама по себе для тестового задания, но при доработке части 2 это упрётся в стену: правила «рабочие часы / шаг / длительность / пересечение» понадобятся и в `store`, и в тестах, и, вероятно, в будущем `update`. Разумный минимум: `StoreBookingRequest` (валидация структуры) + сервис/экшен `BookingService::create(User $user, Room $room, ...)`, возвращающий бронь или бросающий `ValidationException`. `app/Http/Controllers/Controller.php` пустой, `AppServiceProvider` пустой — инфраструктурных ограничений на это нет.

### 23. Тестами покрыт только `index`

[`tests/Feature/BookingIndexTest.php`](tests/Feature/BookingIndexTest.php) — единственный тест (и один unit-каталог с `.gitkeep`). Требование ТЗ «feature-тесты на все правила» пока не выполнено: нет тестов на пересечение, границы, часовые пояса, шаг, длительность, авторство удаления, 422. Текущий тест — хороший шаблон: `RefreshDatabase` + `Sanctum::actingAs($user)` вместо ручных токенов.

---

## Подозрительно, но проблемой не является

Ниже места, которые выглядят как ошибки, но по факту корректны — или где ошибка в другом.

- **`orderByRaw('starts_at DESC')`** ([`BookingController.php:17`](app/Http/Controllers/BookingController.php#L17)) — `raw` без биндингов, но и пользовательского ввода здесь нет. Не инъекция. Стилистически лучше `orderByDesc('starts_at')`, но менять не обязательно.
- **`#[Fillable(...)]` и `#[Hidden(...)]` вместо `$fillable`/`$hidden`** — новый стиль Laravel 13 через атрибуты, а не ошибка. Однако именно `Fillable` на `Booking` делает возможной подмену `user_id` (п. 2): проблема не в атрибуте, а в `$request->all()`.
- **README утверждает: «без `Accept: application/json` запрос без токена вернёт 500 вместо 401».** По коду это не так: в [`bootstrap/app.php:19-21`](bootstrap/app.php#L19-L21) зарегистрирован `shouldRenderJsonWhen(fn ($r) => $r->is('api/*') || $r->expectsJson())`, а обработчик исключений вызывает этот колбэк и для `AuthenticationException` (`unauthenticated()` → `shouldReturnJson()`). Для `/api/*` JSON включён принудительно, значит 401 должен возвращаться и без `Accept`. Утверждение, судя по всему, устарело (осталось от скелета до добавления этого колбэка) — стоит перепроверить на стенде и поправить README. 500 без `Accept` реально получить в другом месте: `store` с несуществующим `room_id` или мусорной датой (пп. 8, 11).
- **`'guard' => ['web']` в [`config/sanctum.php:40`](config/sanctum.php#L40)** — штатное значение; для bearer-токенов Sanctum сначала пробует stateful-гарды, затем токен. Не проблема.
- **`'timezone' => 'UTC'` в [`config/app.php:68`](config/app.php#L68)** — само по себе правильный выбор («храним в UTC, показываем в локальном»). Проблема только в том, что локальное время офиса нигде не применяется (п. 5) и нормализация не выражена явно (п. 12).
- **`timestamp` вместо `timestamptz`** — не ошибка как таковая, если весь код дисциплинированно хранит UTC. Код — не дисциплинирован, отсюда п. 12.
- **`$table->foreignId('office_id')->constrained()`** и **`user_id` FK** — корректно; `restrict` по умолчанию (нельзя удалить офис/пользователя с комнатами/бронями) для этой задачи разумен.
- **`paginate(20)` в `index`** — корректно; контракт (`data`/`meta`/`links`) стандартный, тест на него опирается.
- **`Sanctum::actingAs()` в тестах и хешированные демо-токены в сидере** (`hash('sha256', $token)`) — так и надо: в `personal_access_tokens` хранится хеш, клиент шлёт открытый токен. Не уязвимость, а корректная реализация.
- **Отсутствие `starts_at < ends_at` на уровне БД (CHECK)** — не «пропущенное» ограничение, а следствие того, что такой валидации нет в коде (п. 7). Добавить стоит, но это часть общей доработки.
- **`composer.json` требует `php: ^8.3`, а образ на PHP 8.4** — нормально: констрейнт допускает 8.4.
- **Отсутствие soft deletes у `Booking`** — не проблема для правил задачи (удаление освобождает слот). Но если появится требование аудита отмен, это первое, что придётся добавить: сейчас история отмен не сохраняется.

---

## Гонка (бонусная часть): два одновременных запроса на один слот

Проверка пересечения и `INSERT` не атомарны: между `SELECT` и `INSERT` второй запрос успевает пройти ту же проверку. На последовательных запросах правила соблюдаются, при параллельных — нет.

Варианты решения, от лучшего к худшему:

1. **Exclusion constraint в PostgreSQL** — единственный вариант, который гарантирует инвариант на уровне БД (и защищает от багов в любом будущем коде записи):

   ```sql
   CREATE EXTENSION IF NOT EXISTS btree_gist;

   ALTER TABLE bookings
     ADD CONSTRAINT bookings_no_overlap
     EXCLUDE USING gist (
       room_id WITH =,
       tstzrange(starts_at, ends_at, '[)') WITH &&
     );
   ```

   Требования: колонки должны быть `timestamptz` (иначе `tstzrange` не построить без приведения), а `'[)'` даёт ровно нужную семантику — интервалы, соприкасающиеся концами, не конфликтуют. Нарушение даёт `QueryException` с SQLSTATE `23P01`, его надо перехватить и превратить в 422, как и обычный конфликт.

2. **`SELECT ... FOR UPDATE` внутри транзакции** — блокировка существующих строк не спасает от гонки на «пустом» слоте, поэтому в чистом виде недостаточно; нужен либо advisory lock, либо сериализуемый уровень изоляции.

3. **`pg_advisory_xact_lock(room_id)`** в начале транзакции — сериализует создание броней по комнате. Работает, но блокировки внешние по отношению к данным (нужно не забыть про них в каждом месте записи).

4. **Уникальный индекс по слоту** — не подходит: слоты произвольной длительности, уникальность по `(room_id, starts_at)` не покрывает частичные пересечения.

Практический минимум для сдачи: реализовать п. 1 (он же закрывает п. 13 по индексации) и описать альтернативы в README.

---

## Вне кода

- **README устарел в одном месте** — см. пункт про `Accept` выше.
- **`.env.example`, `compose.yaml`, `docker/start.sh`** — согласованы: `DB_CONNECTION=pgsql`, хост `postgres`, `php artisan serve` на 8000, установка зависимостей при первом старте, автоматическая генерация `APP_KEY`. Проблем не нашёл.
- **Тестовая база** создаётся init-скриптом `docker/create-testing-db.sql`, `phpunit.xml` переключает `DB_DATABASE=testing` — тесты идут на реальном PostgreSQL, поэтому `tstzrange`/`btree_gist` в тестах доступны (что важно для варианта с exclusion constraint).
- **`AGENTS.md` / `CLAUDE.md`** — сгенерированы Laravel Boost. Полезны как конвенции (PHPUnit, Pint, typed-сигнатуры), но содержат неприменимые к проекту инструкции: здесь нет `package.json`/Vite, а скиллы `skills/**` и правила `.ai/rules` в репозитории отсутствуют (появляются только после `php artisan boost:install`).

---

## Предлагаемый порядок исправлений (для части 2)

1. Валидация и структура: `StoreBookingRequest` + явный список полей вместо `$request->all()` (закрывает пп. 2, 8, 21).
2. Автор из `$request->user()`, политика на `delete` (пп. 2, 3).
3. Разбор дат → пояс офиса → проверки «рабочие часы / длительность / шаг» → нормализация в UTC → проверка пересечения строгими неравенствами (пп. 4, 5, 6, 7, 10, 12).
4. Замена `DB::select` на query builder (п. 1) и валидация `exists:rooms,id` (п. 11).
5. Единый формат ошибок 422 через `ValidationException` (п. 9).
6. Миграция: `timestamptz` + индекс (пп. 12, 13); опционально exclusion constraint (гонка).
7. Feature-тесты: пересечения и границы, часовые пояса (Москва/Новосибирск), длительность, шаг, авторство удаления, 422 на мусор (п. 23).
