<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validador ligero para peticiones de controladores.
 */
class Validator
{
    private array $data;
    private array $rules;
    private array $messages;
    private array $errors = [];

    public function __construct(array $data, array $rules, array $messages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
        $this->runValidation();
    }

    public static function make(array $data, array $rules, array $messages = []): self
    {
        return new self($data, $rules, $messages);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * Devuelve los errores organizados por campo: array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Devuelve los errores aplanados (primer mensaje de cada campo) o vacío si pasa.
     */
    public function validate(): array
    {
        $flat = [];
        foreach ($this->errors as $campo => $mensajes) {
            $flat[$campo] = is_array($mensajes) ? ($mensajes[0] ?? '') : (string) $mensajes;
        }
        return $flat;
    }

    private function runValidation(): void
    {
        foreach ($this->rules as $field => $rulesString) {
            $ruleList = is_array($rulesString) ? $rulesString : explode('|', (string) $rulesString);
            $val = $this->data[$field] ?? null;

            foreach ($ruleList as $ruleItem) {
                $ruleItem = trim((string) $ruleItem);
                if ($ruleItem === '') {
                    continue;
                }

                $param = null;
                if (str_contains($ruleItem, ':')) {
                    [$ruleName, $param] = explode(':', $ruleItem, 2);
                } else {
                    $ruleName = $ruleItem;
                }

                $msgKey = "{$field}.{$ruleName}";

                // Regla required
                if ($ruleName === 'required') {
                    if ($val === null || (is_string($val) && trim($val) === '') || (is_array($val) && empty($val))) {
                        $this->addError($field, $this->messages[$msgKey] ?? "El campo {$field} es obligatorio.");
                        break; // Si es requerido y falta, no seguir evaluando otras reglas
                    }
                    continue;
                }

                // Si no es required y el valor está vacío, se omite el resto
                if ($val === null || (is_string($val) && trim($val) === '')) {
                    continue;
                }

                switch ($ruleName) {
                    case 'string':
                        if (!is_string($val)) {
                            $this->addError($field, $this->messages[$msgKey] ?? "El campo {$field} debe ser una cadena.");
                        }
                        break;

                    case 'integer':
                        if (filter_var($val, FILTER_VALIDATE_INT) === false) {
                            $this->addError($field, $this->messages[$msgKey] ?? "El campo {$field} debe ser un número entero.");
                        }
                        break;

                    case 'min_value':
                        $min = (float) $param;
                        if ((float) $val < $min) {
                            $this->addError($field, $this->messages[$msgKey] ?? "El campo {$field} debe ser mayor o igual a {$min}.");
                        }
                        break;

                    case 'max_value':
                        $max = (float) $param;
                        if ((float) $val > $max) {
                            $this->addError($field, $this->messages[$msgKey] ?? "El campo {$field} no debe ser mayor a {$max}.");
                        }
                        break;

                    case 'min_len':
                        $min = (int) $param;
                        if (mb_strlen((string) $val, 'UTF-8') < $min) {
                            $this->addError($field, $this->messages[$msgKey] ?? "El campo {$field} debe tener al menos {$min} caracteres.");
                        }
                        break;

                    case 'max_len':
                        $max = (int) $param;
                        if (mb_strlen((string) $val, 'UTF-8') > $max) {
                            $this->addError($field, $this->messages[$msgKey] ?? "El campo {$field} no debe superar los {$max} caracteres.");
                        }
                        break;

                    case 'date':
                        if (!$this->isDateValid((string) $val)) {
                            $this->addError($field, $this->messages[$msgKey] ?? "El campo {$field} debe ser una fecha válida.");
                        }
                        break;

                    case 'date_not_future':
                        if ($this->isDateValid((string) $val)) {
                            if ((string) $val > date('Y-m-d')) {
                                $this->addError($field, $this->messages[$msgKey] ?? "El campo {$field} no puede ser una fecha futura.");
                            }
                        }
                        break;

                    case 'date_after_or_equal':
                        $otherField = (string) $param;
                        $otherVal = $this->data[$otherField] ?? null;
                        if ($this->isDateValid((string) $val) && $this->isDateValid((string) $otherVal)) {
                            if ((string) $val < (string) $otherVal) {
                                $this->addError($field, $this->messages[$msgKey] ?? "El campo {$field} debe ser igual o posterior a {$otherField}.");
                            }
                        }
                        break;

                    case 'regex':
                        if (!@preg_match((string) $param, (string) $val)) {
                            $this->addError($field, $this->messages[$msgKey] ?? "El formato del campo {$field} no es válido.");
                        }
                        break;

                    case 'in':
                        $allowed = explode(',', (string) $param);
                        if (!in_array((string) $val, $allowed, true)) {
                            $this->addError($field, $this->messages[$msgKey] ?? "El valor del campo {$field} no es válido.");
                        }
                        break;
                }
            }
        }
    }

    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    private function isDateValid(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
