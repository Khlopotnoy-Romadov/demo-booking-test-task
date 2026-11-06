<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Демо — бронирование переговорных</title>
    <style>
        :root {
            --bg: #f5f6f8;
            --card: #ffffff;
            --border: #e2e5ea;
            --text: #191d23;
            --muted: #667085;
            --accent: #2f6feb;
            --accent-dark: #1f56c4;
            --ok: #0f7b57;
            --warn: #a8620a;
            --err: #b42318;
            --radius: 10px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font: 15px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .wrap {
            max-width: 1080px;
            margin: 0 auto;
            padding: 28px 20px 64px;
        }

        h1 {
            margin: 0 0 6px;
            font-size: 24px;
            letter-spacing: -0.01em;
        }

        h2 {
            margin: 0 0 14px;
            font-size: 16px;
            letter-spacing: -0.01em;
        }

        .sub {
            margin: 0 0 24px;
            color: var(--muted);
            font-size: 14px;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px;
            margin-bottom: 18px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        @media (max-width: 860px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        input,
        select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
            color: inherit;
            font: inherit;
        }

        input:focus,
        select:focus {
            outline: 2px solid rgba(47, 111, 235, 0.25);
            border-color: var(--accent);
        }

        .field {
            flex: 1 1 150px;
            min-width: 0;
        }

        button {
            padding: 9px 15px;
            border: 1px solid transparent;
            border-radius: 8px;
            background: var(--accent);
            color: #fff;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }

        button:hover {
            background: var(--accent-dark);
        }

        button:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        button.ghost {
            background: #fff;
            border-color: var(--border);
            color: var(--text);
        }

        button.ghost:hover {
            background: #f1f3f6;
        }

        button.danger {
            background: #fff;
            border-color: #f0c2c0;
            color: var(--err);
        }

        button.danger:hover {
            background: #fdf2f2;
        }

        button.small {
            padding: 5px 10px;
            font-size: 13px;
            font-weight: 500;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th,
        td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--muted);
            font-weight: 600;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .muted {
            color: var(--muted);
        }

        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12.5px;
        }

        .empty {
            padding: 14px 0;
            color: var(--muted);
            font-size: 14px;
        }

        .chip {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            background: #eef1f6;
            color: var(--muted);
        }

        .chip.ok {
            background: #e7f6ef;
            color: var(--ok);
        }

        .chip.warn {
            background: #fdf3e3;
            color: var(--warn);
        }

        .chip.err {
            background: #fdeceb;
            color: var(--err);
        }

        .rooms {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 10px;
        }

        .room {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 12px;
        }

        .room strong {
            display: block;
            font-size: 15px;
        }

        .room span {
            font-size: 13px;
            color: var(--muted);
        }

        ul.rules {
            margin: 0;
            padding-left: 20px;
            font-size: 14px;
        }

        ul.rules li {
            margin-bottom: 5px;
        }

        #log {
            max-height: 320px;
            overflow: auto;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12.5px;
        }

        .entry {
            border-bottom: 1px solid var(--border);
            padding: 8px 0;
        }

        .entry:last-child {
            border-bottom: none;
        }

        .entry pre {
            margin: 6px 0 0;
            padding: 8px;
            background: #f7f8fa;
            border-radius: 6px;
            overflow: auto;
            max-height: 200px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .notice {
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 12px;
            display: none;
        }

        .notice.show {
            display: block;
        }

        .notice.ok {
            background: #e7f6ef;
            color: var(--ok);
        }

        .notice.err {
            background: #fdeceb;
            color: var(--err);
        }

        .notice ul {
            margin: 6px 0 0;
            padding-left: 18px;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Демо: бронирование переговорных</h1>
    <p class="sub">
        Страница ходит в тот же API, что и внешний клиент, с заголовком
        <span class="mono">Authorization: Bearer &lt;токен&gt;</span>.
        Ничего не собирается и не требует Node.js.
    </p>

    <div class="card">
        <div class="row">
            <div class="field" style="flex: 2 1 260px;">
                <label for="token">Токен Sanctum</label>
                <input id="token" class="mono" autocomplete="off" placeholder="anna-token">
            </div>
            <button class="ghost" id="save-token" type="button">Применить токен</button>
            <button class="ghost" type="button" data-token="anna-token">Анна</button>
            <button class="ghost" type="button" data-token="boris-token">Борис</button>
        </div>
        <p class="sub" style="margin: 12px 0 0;">
            Демо-токены из сидера: <span class="mono">anna-token</span> и <span class="mono">boris-token</span>.
            Смените токен на Бориса и попробуйте отменить чужую бронь — получите 403.
        </p>
    </div>

    <div class="grid">
        <section class="card">
            <h2>Переговорные</h2>
            <div class="rooms" id="rooms"></div>
            <p class="sub" style="margin: 14px 0 0;">
                Рабочее время у всех офисов одно — 09:00–20:00, но считается по часовому поясу офиса.
            </p>
        </section>

        <section class="card">
            <h2>Новая бронь</h2>

            <div class="notice" id="form-notice"></div>

            <form id="booking-form">
                <div class="row" style="margin-bottom: 10px;">
                    <div class="field">
                        <label for="room_id">Комната</label>
                        <select id="room_id" name="room_id" required></select>
                    </div>
                    <div class="field">
                        <label for="title">Название</label>
                        <input id="title" name="title" value="Планёрка" required maxlength="255">
                    </div>
                </div>

                <div class="row" style="margin-bottom: 10px;">
                    <div class="field">
                        <label for="date">Дата</label>
                        <input id="date" name="date" type="date" required>
                    </div>
                    <div class="field">
                        <label for="from">Начало</label>
                        <input id="from" name="from" type="time" step="60" value="10:00" required>
                    </div>
                    <div class="field">
                        <label for="to">Конец</label>
                        <input id="to" name="to" type="time" step="60" value="11:00" required>
                    </div>
                </div>

                <p class="sub mono" id="payload-preview" style="margin: 0 0 12px; word-break: break-all;"></p>

                <button type="submit" id="submit-booking">Создать бронь</button>
            </form>
        </section>
    </div>

    <section class="card">
        <div class="row" style="margin-bottom: 14px;">
            <h2 style="margin: 0; flex: 1;">Мои брони</h2>
            <button class="ghost small" type="button" id="refresh-bookings">Обновить</button>
        </div>
        <div id="bookings"></div>
    </section>

    <section class="card">
        <div class="row" style="margin-bottom: 14px;">
            <h2 style="margin: 0; flex: 1;">Брони комнаты на сегодня</h2>
            <div class="field" style="flex: 0 1 220px; margin: 0;">
                <select id="today-room"></select>
            </div>
            <button class="ghost small" type="button" id="refresh-today">Показать</button>
        </div>
        <div id="today"></div>
        <p class="sub" style="margin: 12px 0 0;">
            Метод <span class="mono">GET /api/rooms/{room}/bookings/today</span>. Отдаёт время в UTC,
            потому что «сегодня» и форматирование берутся из конфигурации приложения.
        </p>
    </section>

    <section class="card">
        <h2>Правила бронирования</h2>
        <ul class="rules">
            <li>Интервалы не пересекаются; встречи впритык разрешены: 10:00–11:00 и 11:00–12:00 не конфликтуют.</li>
            <li>Длительность от 15 минут до 4 часов, начало и конец кратны 15 минутам.</li>
            <li>Бронь целиком внутри 09:00–20:00 по часовому поясу офиса комнаты.</li>
            <li>Время принимается в ISO 8601 с любым смещением и хранится как момент времени.</li>
            <li>Автор брони — владелец токена, отменить может только он.</li>
            <li>Ограничений на даты нет: прошлое и далёкое будущее допустимы.</li>
        </ul>
    </section>

    <section class="card">
        <h2>Журнал запросов</h2>
        <div id="log"><div class="empty">Пока пусто.</div></div>
    </section>
</div>

<script type="application/json" id="rooms-data">@json($rooms)</script>

<script>
    (function () {
        'use strict';

        const state = {
            token: localStorage.getItem('demo.token') || 'anna-token',
            rooms: JSON.parse(document.getElementById('rooms-data').textContent),
            entries: [],
        };

        const el = (id) => document.getElementById(id);

        const roomById = (id) => state.rooms.find((room) => room.id === Number(id)) || null;

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function chipClass(status) {
            if (status >= 200 && status < 300) {
                return 'ok';
            }

            if (status >= 400 && status < 500) {
                return 'warn';
            }

            return 'err';
        }

        /**
         * UTC offset of the timezone on the given date, e.g. "+03:00".
         */
        function offsetOf(timeZone, date) {
            const probe = new Date(date + 'T12:00:00Z');

            try {
                const part = new Intl.DateTimeFormat('en-US', {
                    timeZone: timeZone,
                    timeZoneName: 'longOffset',
                }).formatToParts(probe).find((item) => item.type === 'timeZoneName');

                const value = part ? part.value.replace('GMT', '') : '';

                if (/^[+-]\d{2}:\d{2}$/.test(value)) {
                    return value;
                }

                if (value === '') {
                    return '+00:00';
                }
            } catch (error) {
                // Older engines: fall through to the offset calculation below.
            }

            try {
                const parts = new Intl.DateTimeFormat('en-US', {
                    timeZone: timeZone,
                    hour12: false,
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                }).formatToParts(probe);

                const read = (type) => Number((parts.find((item) => item.type === type) || {}).value);
                const asUtc = Date.UTC(
                    read('year'), read('month') - 1, read('day'),
                    read('hour') % 24, read('minute'), read('second')
                );
                const minutes = Math.round((asUtc - probe.getTime()) / 60000);
                const sign = minutes < 0 ? '-' : '+';
                const abs = Math.abs(minutes);

                return sign
                    + String(Math.floor(abs / 60)).padStart(2, '0')
                    + ':' + String(abs % 60).padStart(2, '0');
            } catch (error) {
                return '+00:00';
            }
        }

        function formatMoment(iso, timeZone, withDate) {
            const options = {
                timeZone: timeZone,
                hour: '2-digit',
                minute: '2-digit',
            };

            if (withDate) {
                options.day = '2-digit';
                options.month = '2-digit';
            }

            try {
                return new Intl.DateTimeFormat('ru-RU', options).format(new Date(iso));
            } catch (error) {
                return iso;
            }
        }

        function formatRange(booking) {
            const room = roomById(booking.room_id);
            const timeZone = room ? room.timezone : 'UTC';

            return formatMoment(booking.starts_at, timeZone, true)
                + '–' + formatMoment(booking.ends_at, timeZone, false);
        }

        function log(method, path, status, body) {
            state.entries.unshift({ method, path, status, body });

            if (state.entries.length > 25) {
                state.entries.pop();
            }

            renderLog();
        }

        function renderLog() {
            const container = el('log');

            if (state.entries.length === 0) {
                container.innerHTML = '<div class="empty">Пока пусто.</div>';

                return;
            }

            container.innerHTML = state.entries.map((entry) => {
                const payload = entry.body === null || entry.body === undefined
                    ? ''
                    : '<pre>' + escapeHtml(JSON.stringify(entry.body, null, 2)) + '</pre>';

                return '<div class="entry">'
                    + '<span class="chip ' + chipClass(entry.status) + '">' + entry.status + '</span> '
                    + '<strong class="mono">' + entry.method + '</strong> '
                    + '<span class="mono">' + escapeHtml(entry.path) + '</span>'
                    + payload
                    + '</div>';
            }).join('');
        }

        async function api(method, path, body) {
            const headers = {
                'Accept': 'application/json',
            };

            if (state.token) {
                headers['Authorization'] = 'Bearer ' + state.token;
            }

            const options = { method: method, headers: headers };

            if (body !== undefined) {
                headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(body);
            }

            let response;
            let data = null;

            try {
                response = await fetch(path, options);
            } catch (error) {
                log(method, path, 0, { error: 'Сетевая ошибка: ' + error.message });

                return { status: 0, data: null };
            }

            if (response.status !== 204) {
                try {
                    data = await response.json();
                } catch (error) {
                    data = null;
                }
            }

            log(method, path, response.status, data);

            return { status: response.status, data: data };
        }

        function showNotice(container, kind, html) {
            container.className = 'notice show ' + kind;
            container.innerHTML = html;
        }

        function hideNotice(container) {
            container.className = 'notice';
            container.innerHTML = '';
        }

        function validationMessages(body) {
            if (!body || !body.errors) {
                return [];
            }

            const messages = [];

            Object.keys(body.errors).forEach((field) => {
                const list = body.errors[field];

                if (Array.isArray(list)) {
                    list.forEach((message) => messages.push('<span class="mono">' + escapeHtml(field) + '</span>: ' + escapeHtml(message)));
                }
            });

            return messages;
        }

        function renderRooms() {
            const container = el('rooms');

            if (state.rooms.length === 0) {
                container.innerHTML = '<div class="empty">Комнат нет. Выполните <span class="mono">php artisan migrate --seed</span>.</div>';

                return;
            }

            container.innerHTML = state.rooms.map((room) =>
                '<div class="room">'
                + '<strong>' + escapeHtml(room.office) + ' · ' + escapeHtml(room.name) + '</strong>'
                + '<span>' + room.capacity + ' мест · ' + escapeHtml(room.timezone) + '</span>'
                + '</div>'
            ).join('');
        }

        function renderRoomSelects() {
            const options = state.rooms.map((room) =>
                '<option value="' + room.id + '">'
                + escapeHtml(room.office) + ' · ' + escapeHtml(room.name)
                + ' (' + escapeHtml(room.timezone) + ')'
                + '</option>'
            ).join('');

            el('room_id').innerHTML = options;
            el('today-room').innerHTML = options;
        }

        function currentPayload() {
            const room = roomById(el('room_id').value);
            const date = el('date').value;
            const from = el('from').value;
            const to = el('to').value;

            if (!room || !date || !from || !to) {
                return null;
            }

            const offset = offsetOf(room.timezone, date);

            return {
                room_id: room.id,
                title: el('title').value,
                starts_at: date + 'T' + from + ':00' + offset,
                ends_at: date + 'T' + to + ':00' + offset,
            };
        }

        function renderPayloadPreview() {
            const payload = currentPayload();
            const preview = el('payload-preview');

            if (!payload) {
                preview.textContent = '';

                return;
            }

            preview.textContent = 'starts_at: ' + payload.starts_at + '   ends_at: ' + payload.ends_at;
        }

        function renderBookings(bookings) {
            const container = el('bookings');

            if (!bookings || bookings.length === 0) {
                container.innerHTML = '<div class="empty">Броней нет. Создайте первую в форме выше.</div>';

                return;
            }

            container.innerHTML = '<table><thead><tr>'
                + '<th>Время</th><th>Комната</th><th>Название</th><th></th>'
                + '</tr></thead><tbody>'
                + bookings.map((booking) => {
                    const room = roomById(booking.room_id);

                    return '<tr>'
                        + '<td class="mono">' + escapeHtml(formatRange(booking)) + '</td>'
                        + '<td>' + (room ? escapeHtml(room.office + ' · ' + room.name) : '#' + booking.room_id) + '</td>'
                        + '<td>' + escapeHtml(booking.title) + '</td>'
                        + '<td style="text-align: right;">'
                        + '<button class="danger small" type="button" data-cancel="' + booking.id + '">Отменить</button>'
                        + '</td>'
                        + '</tr>';
                }).join('')
                + '</tbody></table>';
        }

        async function refreshBookings() {
            const result = await api('GET', '/api/bookings');

            if (result.status === 401) {
                el('bookings').innerHTML = '<div class="empty">401 — токен не принят. Проверьте значение выше.</div>';

                return;
            }

            if (result.status !== 200 || !result.data) {
                el('bookings').innerHTML = '<div class="empty">Не удалось загрузить брони.</div>';

                return;
            }

            renderBookings(result.data.data || []);
        }

        async function cancelBooking(id) {
            const result = await api('DELETE', '/api/bookings/' + id);

            if (result.status === 204) {
                await refreshBookings();
                await refreshToday();

                return;
            }

            if (result.status === 403) {
                window.alert('403: отменить бронь может только её автор.');

                return;
            }

            window.alert('Не удалось отменить: ' + result.status);
        }

        async function refreshToday() {
            const roomId = el('today-room').value;
            const container = el('today');

            if (!roomId) {
                container.innerHTML = '<div class="empty">Выберите комнату.</div>';

                return;
            }

            const result = await api('GET', '/api/rooms/' + roomId + '/bookings/today');

            if (result.status !== 200 || !Array.isArray(result.data)) {
                container.innerHTML = '<div class="empty">Не удалось загрузить расписание.</div>';

                return;
            }

            if (result.data.length === 0) {
                container.innerHTML = '<div class="empty">На сегодня броней нет.</div>';

                return;
            }

            container.innerHTML = '<table><thead><tr><th>Время</th><th>Автор</th></tr></thead><tbody>'
                + result.data.map((row) =>
                    '<tr><td class="mono">' + escapeHtml(row.time) + '</td><td>' + escapeHtml(row.author) + '</td></tr>'
                ).join('')
                + '</tbody></table>';
        }

        async function submitBooking(event) {
            event.preventDefault();

            const notice = el('form-notice');
            const button = el('submit-booking');
            const payload = currentPayload();

            hideNotice(notice);

            if (!payload) {
                showNotice(notice, 'err', 'Заполните комнату, дату и время.');

                return;
            }

            button.disabled = true;

            const result = await api('POST', '/api/bookings', payload);

            button.disabled = false;

            if (result.status === 201) {
                showNotice(notice, 'ok', 'Бронь создана: ' + escapeHtml(payload.starts_at) + ' — ' + escapeHtml(payload.ends_at));
                await refreshBookings();
                await refreshToday();

                return;
            }

            const messages = validationMessages(result.data);

            if (messages.length > 0) {
                showNotice(notice, 'err', '422 — бронь не создана:<ul><li>' + messages.join('</li><li>') + '</li></ul>');

                return;
            }

            showNotice(notice, 'err', 'Ответ ' + result.status + '.');
        }

        function applyToken(token) {
            state.token = token;
            localStorage.setItem('demo.token', token);
            el('token').value = token;

            refreshBookings();
            refreshToday();
        }

        function init() {
            el('token').value = state.token;

            renderRooms();
            renderRoomSelects();

            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);

            const local = {
                year: tomorrow.getFullYear(),
                month: String(tomorrow.getMonth() + 1).padStart(2, '0'),
                day: String(tomorrow.getDate()).padStart(2, '0'),
            };

            el('date').value = local.year + '-' + local.month + '-' + local.day;

            renderPayloadPreview();

            el('save-token').addEventListener('click', () => applyToken(el('token').value.trim()));

            document.querySelectorAll('[data-token]').forEach((button) => {
                button.addEventListener('click', () => applyToken(button.dataset.token));
            });

            el('booking-form').addEventListener('submit', submitBooking);
            ['room_id', 'date', 'from', 'to'].forEach((id) => {
                el(id).addEventListener('change', renderPayloadPreview);
            });

            el('refresh-bookings').addEventListener('click', refreshBookings);
            el('refresh-today').addEventListener('click', refreshToday);
            el('today-room').addEventListener('change', refreshToday);

            el('bookings').addEventListener('click', (event) => {
                const button = event.target.closest('[data-cancel]');

                if (button) {
                    cancelBooking(button.dataset.cancel);
                }
            });

            refreshBookings();
            refreshToday();
        }

        document.addEventListener('DOMContentLoaded', init);
    })();
</script>
</body>
</html>
