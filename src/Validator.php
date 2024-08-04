<?php

namespace wesone\Wyne;

define('X_FILTER_VALIDATE_STRING', 'X_FILTER_VALIDATE_STRING');
define('X_FILTER_VALIDATE_ARRAY', 'X_FILTER_VALIDATE_ARRAY');

// https://www.php.net/manual/de/filter.filters.php

class ValidationException extends \Exception
{
}

class Validator
{
    private static $customTypes = [
        X_FILTER_VALIDATE_STRING,
        X_FILTER_VALIDATE_ARRAY
    ];

    private static function isCustomType(string $type): bool
    {
        return in_array($type, self::$customTypes);
    }

    private static function stringValidation(mixed $value, array $options, ?int $flags)
    {
        if (!isset($value))
            return array_key_exists('default', $options)
                ? $options['default']
                : false;

        if (!is_string($value))
            return false;

        if (isset($options['trim']) && $options['trim'] === true)
            $value = trim($value);

        if (isset($options['toUpperCase']) && $options['toUpperCase'] === true)
            $value = strtoupper($value);
        if (isset($options['toLowerCase']) && $options['toLowerCase'] === true)
            $value = strtolower($value);

        if (isset($options['oneOf']) && is_array($options['oneOf']) && !in_array($value, $options['oneOf']))
            return false;

        $length = mb_strlen($value);
        if (isset($options['length']) && $length !== $options['length'])
            return false;
        if (!isset($options['min_length']))
            $options['min_length'] = 1; // default min_length of a string is 1
        if ($length < $options['min_length'])
            return false;
        if (isset($options['max_length']) && $length > $options['max_length'])
            return false;

        return $value;
    }

    private static function arrayValidation(mixed $array, array $options, ?int $flags)
    {
        if (!isset($array))
            return array_key_exists('default', $options)
                ? $options['default']
                : false;

        if (is_string($array)) // may be JSON
            $array = json_decode($array, true); // will be null if decoding fails
        if (!is_array($array))
            return false;

        $length = count($array);
        if (isset($options['length']) && $length !== $options['length'])
            return false;
        if (isset($options['min_length']) && $length < $options['min_length'])
            return false;
        if (isset($options['max_length']) && $length > $options['max_length'])
            return false;

        if (isset($options['shape']))
            $array = self::validate($array, $options['shape']);
        if (isset($options['of']))
            for ($i = 0; $i < count($array); $i++) {
                try {
                    $array[$i] = self::validate($array[$i], $options['of']);
                } catch (\Exception $e) {
                    throw new ValidationException($e->getMessage() . " Array index '{$i}'.");
                }
            }

        return $array;
    }

    private static function validateCustom($value, string $type, array $options, ?int $flags)
    {
        switch ($type) {
            case X_FILTER_VALIDATE_STRING:
                return self::stringValidation($value, $options, $flags);
            case X_FILTER_VALIDATE_ARRAY:
                return self::arrayValidation($value, $options, $flags);
            default:
                return false;
        }
    }

    public static function validate(array $source, array $schema): array
    {
        $result = [];
        foreach ($schema as $key => $filter) {
            if (is_array($filter)) {
                @['type' => $type, 'options' => $options, 'flags' => $flags] = $filter;
                if (!isset($options))
                    $options = [];
                if (!isset($flags))
                    $flags = null;
            } else {
                $type = $filter;
                $options = [];
                $flags = null;
            }

            if (!array_key_exists($key, $source) && is_array($options) && @$options['optional'] === true)
                continue;

            $value = @$source[$key];

            if ($value === null && is_array($options) && @$options['nullable'] === true) {
                $result[$key] = $value;
                continue;
            }

            $validated = self::isCustomType($type)
                ? self::validateCustom($value, $type, $options, $flags)
                : filter_var($value, $type, [
                    'options' => $type === FILTER_CALLBACK && !is_callable($options)
                        ? $options['callback']
                        : $options,
                    'flags' => $flags
                ]);

            if ($validated === false) {
                // if $validated === false but type is FILTER_VALIDATE_BOOLEAN, 
                // $source[$key] is set (not undefined or null)
                // and boolval($source[$key]) === false,
                // then $validated === false is NOT a failed validation
                if ($type !== FILTER_VALIDATE_BOOLEAN || !isset($value) || ($value !== false && $value !== "0" && $value !== "false" && $value !== "off" && $value !== "no"))
                    throw new ValidationException("Field '{$key}' failed validation.");
            }

            $result[$key] = $validated;
        }
        return $result;
    }
}
