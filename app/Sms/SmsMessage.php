<?php

namespace App\Sms;

final readonly class SmsMessage
{
    /**
     * @param  string  $template  key in services.msg91.templates (DLT template)
     * @param  array<string, string>  $variables  template variables (var1, var2... for MSG91)
     */
    public function __construct(
        public string $text,
        public string $template,
        public array $variables = [],
    ) {}
}
