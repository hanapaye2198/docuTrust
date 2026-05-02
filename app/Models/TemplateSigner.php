<?php

namespace App\Models;

use App\Enums\TemplateRoleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateSigner extends Model
{
    /** @use HasFactory<\Database\Factories\TemplateSignerFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'template_id',
        'role_name',
        'role_type',
        'signing_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role_type' => TemplateRoleType::class,
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
