<?php

namespace AutoDudes\AiSuite\Exception;

class AiSuiteException extends \Exception
{
    protected string $template;
    protected string $messageKey;
    protected string $titleKey;

    protected string $returnUrl;

    public function __construct(
        string $template = "",
        string $messageKey = "",
        string $titleKey = "",
        string $message = "",
        string $returnUrl = "",
        int $code = 0,
        \Throwable $previous = null
    ) {
        $this->template = $template;
        $this->messageKey = $messageKey;
        $this->titleKey = empty($titleKey) ? 'aiSuite.error.default.title' : $titleKey;
        $this->returnUrl = $returnUrl;
        parent::__construct($message, $code, $previous);
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getMessageKey(): string
    {
        return $this->messageKey;
    }

    public function getTitleKey(): string
    {
        return $this->titleKey;
    }

    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }
}
