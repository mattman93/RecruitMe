<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFormPreference extends Model
{
    protected $fillable = [
        'user_id',
        'field_identifier',
        'field_type',
        'question_text',
        'response_data',
        'confidence_score',
        'use_count',
    ];

    protected $casts = [
        'response_data' => 'json',
        'confidence_score' => 'integer',
        'use_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Find preference by question pattern matching
     */
    public static function findByQuestionPattern(int $userId, string $questionText): ?self
    {
        // First try exact field identifier match
        $preference = self::where('user_id', $userId)
            ->where('field_identifier', self::generateFieldIdentifier($questionText))
            ->orderBy('confidence_score', 'desc')
            ->first();

        if ($preference) {
            return $preference;
        }

        // Then try fuzzy matching on question text
        return self::where('user_id', $userId)
            ->where('question_text', 'LIKE', '%' . str_replace(' ', '%', $questionText) . '%')
            ->orderBy('confidence_score', 'desc')
            ->first();
    }

    /**
     * Generate a standardized field identifier from question text
     */
    public static function generateFieldIdentifier(string $questionText): string
    {
        // Convert to lowercase and extract key terms
        $text = strtolower($questionText);
        
        // Common question patterns
        if (str_contains($text, 'authorized to work') || str_contains($text, 'work authorization')) {
            return 'work_authorization';
        }
        if (str_contains($text, 'visa sponsorship') || str_contains($text, 'require sponsorship')) {
            return 'visa_sponsorship';
        }
        if (str_contains($text, 'background check') || str_contains($text, 'criminal background')) {
            return 'background_check';
        }
        if (str_contains($text, 'drug test') || str_contains($text, 'substance screening')) {
            return 'drug_screening';
        }
        if (str_contains($text, 'remote work') || str_contains($text, 'work remotely')) {
            return 'remote_work';
        }
        if (str_contains($text, 'relocate') || str_contains($text, 'relocation')) {
            return 'willing_to_relocate';
        }
        
        // Fallback: create identifier from key words
        $words = preg_split('/\s+/', $text);
        $keyWords = array_filter($words, function($word) {
            return strlen($word) > 3 && !in_array($word, ['the', 'and', 'for', 'are', 'you', 'can', 'will', 'have', 'this', 'that']);
        });
        
        return implode('_', array_slice($keyWords, 0, 3));
    }

    /**
     * Store or update user preference
     */
    public static function storePreference(int $userId, string $questionText, string $fieldType, $responseData): self
    {
        $fieldIdentifier = self::generateFieldIdentifier($questionText);
        
        $preference = self::updateOrCreate(
            [
                'user_id' => $userId,
                'field_identifier' => $fieldIdentifier,
            ],
            [
                'field_type' => $fieldType,
                'question_text' => $questionText,
                'response_data' => $responseData,
                'confidence_score' => 100,
                'use_count' => 1,
            ]
        );

        // If updating existing preference, increment use count
        if (!$preference->wasRecentlyCreated) {
            $preference->increment('use_count');
        }

        return $preference;
    }
}
