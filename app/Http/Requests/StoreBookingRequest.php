<?php

namespace App\Http\Requests;

use App\Models\Booking;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    /**
     * The room the booking is made for, resolved once for the whole request.
     */
    protected ?Room $bookingRoom = null;

    /**
     * The parsed start of the booking in UTC.
     */
    protected ?CarbonImmutable $startsAtUtc = null;

    /**
     * The parsed end of the booking in UTC.
     */
    protected ?CarbonImmutable $endsAtUtc = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $rules = [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'title' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
        ];

        if ($this->input('starts_at') !== null && $this->input('ends_at') !== null) {
            $rules['ends_at'][] = 'after:starts_at';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'room_id.required' => 'Укажите переговорную комнату.',
            'room_id.integer' => 'Идентификатор комнаты должен быть целым числом.',
            'room_id.exists' => 'Такой переговорной комнаты не существует.',
            'title.required' => 'Укажите название встречи.',
            'title.string' => 'Название встречи должно быть строкой.',
            'title.max' => 'Название встречи не может быть длиннее 255 символов.',
            'starts_at.required' => 'Укажите время начала в формате ISO 8601.',
            'starts_at.date' => 'Время начала должно быть датой в формате ISO 8601.',
            'ends_at.required' => 'Укажите время окончания в формате ISO 8601.',
            'ends_at.date' => 'Время окончания должно быть датой в формате ISO 8601.',
            'ends_at.after' => 'Время окончания должно быть позже времени начала.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'room_id' => 'переговорная комната',
            'title' => 'название встречи',
            'starts_at' => 'время начала',
            'ends_at' => 'время окончания',
        ];
    }

    /**
     * Configure the validator with the booking rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->applyBookingRules($validator);
        });
    }

    /**
     * The room the booking is made for.
     */
    public function room(): Room
    {
        if ($this->bookingRoom === null) {
            /** @var Room $room */
            $room = Room::with('office')->findOrFail($this->integer('room_id'));

            $this->bookingRoom = $room;
        }

        return $this->bookingRoom;
    }

    /**
     * The interval that was actually validated.
     *
     * The controller must persist exactly these moments: re-parsing the raw input
     * outside of validation could silently accept an interval that was never
     * checked against the booking rules.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function validatedInterval(): array
    {
        if ($this->startsAtUtc === null || $this->endsAtUtc === null) {
            throw ValidationException::withMessages([
                'starts_at' => 'Не удалось проверить время брони. Проверьте формат дат.',
            ]);
        }

        return [$this->startsAtUtc, $this->endsAtUtc];
    }

    /**
     * Check the booking rules that cannot be expressed as plain validation rules.
     */
    protected function applyBookingRules(Validator $validator): void
    {
        $room = $this->room();
        $timezone = $room->office->timezone;

        try {
            $this->startsAtUtc = CarbonImmutable::parse($this->input('starts_at'), $timezone)->utc();
            $this->endsAtUtc = CarbonImmutable::parse($this->input('ends_at'), $timezone)->utc();
        } catch (\Throwable) {
            $validator->errors()->add(
                'starts_at',
                'Не удалось разобрать время. Пришлите дату в формате ISO 8601, например 2026-11-02T10:00:00+03:00.'
            );

            return;
        }

        $startsAt = $this->startsAtUtc->setTimezone($timezone);
        $endsAt = $this->endsAtUtc->setTimezone($timezone);

        $localStart = $startsAt->format('H:i');
        $localEnd = $endsAt->format('H:i');

        if ($startsAt->lt($startsAt->setTime(9, 0)) || $endsAt->gt($startsAt->setTime(20, 0))) {
            $validator->errors()->add(
                'starts_at',
                "Бронь должна полностью укладываться в рабочее время офиса с 09:00 до 20:00 (пояс {$timezone}): {$localStart}–{$localEnd}."
            );
        }

        $durationMinutes = (int) $startsAt->diffInMinutes($endsAt);

        if ($durationMinutes < Booking::MIN_DURATION_MINUTES || $durationMinutes > Booking::MAX_DURATION_MINUTES) {
            $validator->errors()->add(
                'ends_at',
                sprintf(
                    'Длительность встречи должна быть от %d минут до %d часов, а сейчас %d минут.',
                    Booking::MIN_DURATION_MINUTES,
                    intdiv(Booking::MAX_DURATION_MINUTES, 60),
                    $durationMinutes
                )
            );
        }

        if ($startsAt->minute % Booking::SLOT_STEP_MINUTES !== 0 || $startsAt->second !== 0) {
            $validator->errors()->add(
                'starts_at',
                'Время начала должно быть кратно 15 минутам, например 10:00 или 10:15.'
            );
        }

        if ($endsAt->minute % Booking::SLOT_STEP_MINUTES !== 0 || $endsAt->second !== 0) {
            $validator->errors()->add(
                'ends_at',
                'Время окончания должно быть кратно 15 минутам, например 11:00 или 11:15.'
            );
        }

        if ($this->hasConflict($this->startsAtUtc, $this->endsAtUtc, $room->id)) {
            $validator->errors()->add(
                'starts_at',
                "Комната занята: на выбранное время уже есть бронь с {$localStart}-{$localEnd}."
            );
        }
    }

    /**
     * Check whether a booking for the room overlaps the given interval.
     *
     * Intervals are half-open: back-to-back meetings (10:00–11:00 and 11:00–12:00)
     * do not conflict.
     */
    protected function hasConflict(CarbonImmutable $startsAt, CarbonImmutable $endsAt, int $roomId): bool
    {
        return Booking::query()
            ->where('room_id', $roomId)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();
    }
}
