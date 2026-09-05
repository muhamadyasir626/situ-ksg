<?php

namespace App\Models;

use Database\Factories\RiskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $report_id
 * @property string $category
 * @property string $narrative
 * @property string $status
 * @property string|null $pic
 * @property Carbon|null $due_date
 * @property Carbon|null $resolved_at
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'report_id', 'category', 'narrative',
    'status', 'pic', 'due_date', 'resolved_at', 'updated_by',
])]
class Risk extends Model
{
    /** @use HasFactory<RiskFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * Kategori Identifikasi Risiko (kolom K-N, §13.4) — hanya berlaku untuk kelompok IKU (§14.4).
     */
    public const CATEGORY_EXTERNAL = 'Eksternal/Kemitraan';

    public const CATEGORY_STRATEGIC = 'Strategis';

    public const CATEGORY_FRAUD = 'Kecurangan';

    public const CATEGORY_POLICY = 'Kebijakan Internal/Eksternal';

    /**
     * @return array<int, string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_EXTERNAL,
            self::CATEGORY_STRATEGIC,
            self::CATEGORY_FRAUD,
            self::CATEGORY_POLICY,
        ];
    }

    public const STATUS_BARU = 'Baru';

    public const STATUS_DITINDAKLANJUTI = 'Ditindaklanjuti';

    public const STATUS_SELESAI = 'Selesai';

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [self::STATUS_BARU, self::STATUS_DITINDAKLANJUTI, self::STATUS_SELESAI];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('risk')
            ->logOnly(['status', 'pic', 'due_date'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'Risiko dicatat',
                'updated' => 'Tindak lanjut risiko diperbarui',
                'deleted' => 'Risiko dihapus',
                default => $eventName,
            });
    }

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Report, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
