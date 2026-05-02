<?php

namespace App\Models;

use App\Enums\SignatureFieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateField extends Model
{
    /** @use HasFactory<\Database\Factories\TemplateFieldFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'template_id',
        'role_name',
        'type',
        'position_data',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SignatureFieldType::class,
            'position_data' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
