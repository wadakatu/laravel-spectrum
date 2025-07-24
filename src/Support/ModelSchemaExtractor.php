<?php

namespace LaravelSpectrum\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ModelSchemaExtractor
{
    public function extractSchema(string $modelClass): array
    {
        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return ['type' => 'object', 'properties' => []];
        }

        try {
            $instance = new $modelClass;
            $table = $instance->getTable();
            $properties = [];

            // データベーススキーマから基本的なカラム情報を取得
            if (Schema::hasTable($table)) {
                $columns = Schema::getColumnListing($table);

                foreach ($columns as $column) {
                    $type = $this->getColumnType($table, $column);
                    $properties[$column] = [
                        'type' => $this->mapDatabaseTypeToOpenApi($type),
                        'description' => $this->getColumnDescription($table, $column),
                    ];
                }
            }

            // $castsプロパティから型情報を補完
            $casts = $instance->getCasts();
            foreach ($casts as $attribute => $cast) {
                if (isset($properties[$attribute])) {
                    $properties[$attribute]['type'] = $this->mapCastToOpenApi($cast);

                    // 日付型の場合はフォーマットを追加
                    if (in_array($cast, ['date', 'datetime', 'timestamp'])) {
                        $properties[$attribute]['format'] = 'date-time';
                    }
                }
            }

            // $hiddenプロパティを除外
            $hidden = $instance->getHidden();
            foreach ($hidden as $hiddenAttribute) {
                unset($properties[$hiddenAttribute]);
            }

            // $appendsプロパティを追加
            $appends = $instance->getAppends();
            foreach ($appends as $append) {
                // アクセサメソッドが存在するか確認
                $accessorMethod = 'get'.str_replace('_', '', ucwords($append, '_')).'Attribute';
                if (method_exists($instance, $accessorMethod)) {
                    $properties[$append] = [
                        'type' => 'string', // デフォルトはstring、実装で推測可能
                        'description' => 'Computed attribute',
                    ];
                }
            }

            // $fillableまたは$guardedから編集可能フィールドを判定
            $fillable = $instance->getFillable();
            $guarded = $instance->getGuarded();

            foreach ($properties as $key => &$property) {
                if (! empty($fillable)) {
                    $property['readOnly'] = ! in_array($key, $fillable);
                } elseif (! empty($guarded) && $guarded !== ['*']) {
                    $property['readOnly'] = in_array($key, $guarded);
                }
            }

            return [
                'type' => 'object',
                'properties' => $properties,
                'description' => "Model: {$modelClass}",
            ];
        } catch (\Exception $e) {
            return ['type' => 'object', 'properties' => []];
        }
    }

    private function getColumnType(string $table, string $column): string
    {
        try {
            // Laravel 9以降のgetColumnTypeメソッドを使用
            return Schema::getColumnType($table, $column);
        } catch (\Exception $e) {
            // エラーの場合はデフォルトで'string'を返す
            return 'string';
        }
    }

    private function getColumnDescription(string $table, string $column): string
    {
        try {
            // MySQLの場合、information_schemaからコメントを取得
            $databaseName = DB::connection()->getDatabaseName();
            $driver = DB::connection()->getDriverName();

            if ($driver === 'mysql') {
                $result = DB::selectOne(
                    'SELECT COLUMN_COMMENT as comment 
                     FROM information_schema.COLUMNS 
                     WHERE TABLE_SCHEMA = ? 
                     AND TABLE_NAME = ? 
                     AND COLUMN_NAME = ?',
                    [$databaseName, $table, $column]
                );

                if ($result && $result->comment) {
                    return $result->comment;
                }
            }
        } catch (\Exception $e) {
            // エラーの場合は空の説明を返す
        }

        return '';
    }

    private function mapDatabaseTypeToOpenApi(string $dbType): string
    {
        $mapping = [
            'integer' => 'integer',
            'int' => 'integer',
            'bigint' => 'integer',
            'biginteger' => 'integer',
            'smallint' => 'integer',
            'smallinteger' => 'integer',
            'tinyint' => 'integer',
            'tinyinteger' => 'integer',
            'decimal' => 'number',
            'float' => 'number',
            'double' => 'number',
            'string' => 'string',
            'varchar' => 'string',
            'char' => 'string',
            'text' => 'string',
            'longtext' => 'string',
            'mediumtext' => 'string',
            'tinytext' => 'string',
            'boolean' => 'boolean',
            'bool' => 'boolean',
            'date' => 'string',
            'datetime' => 'string',
            'timestamp' => 'string',
            'time' => 'string',
            'json' => 'object',
            'jsonb' => 'object',
            'array' => 'array',
            'uuid' => 'string',
            'guid' => 'string',
            'binary' => 'string',
            'blob' => 'string',
        ];

        return $mapping[strtolower($dbType)] ?? 'string';
    }

    private function mapCastToOpenApi(string $cast): string
    {
        // カスタムキャストクラスの場合
        if (str_contains($cast, ':')) {
            [$castType] = explode(':', $cast);
            $cast = $castType;
        }

        $mapping = [
            'int' => 'integer',
            'integer' => 'integer',
            'real' => 'number',
            'float' => 'number',
            'double' => 'number',
            'decimal' => 'number',
            'string' => 'string',
            'bool' => 'boolean',
            'boolean' => 'boolean',
            'object' => 'object',
            'array' => 'array',
            'json' => 'object',
            'collection' => 'array',
            'date' => 'string',
            'datetime' => 'string',
            'timestamp' => 'string',
            'immutable_date' => 'string',
            'immutable_datetime' => 'string',
        ];

        // Enumキャストの場合
        if (str_contains($cast, '\\') && enum_exists($cast)) {
            return 'string'; // またはenumのタイプに応じて
        }

        return $mapping[$cast] ?? 'string';
    }
}
