<?php

namespace App\Services\Communication;

use App\Contracts\CircleServiceContract;
use App\Mail\TemplateMailable;
use App\Models\Circles\Circle;
use App\Models\Communication\EmailTemplate;
use App\Services\Circles\Concerns\HasNoContainerComponent;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class EmailServiceHandler implements CircleServiceContract
{
    use HasNoContainerComponent;

    public function boot(Circle $circle): void
    {
        //
    }

    public function getKey(): string
    {
        return 'email';
    }

    public function getPermissions(): array
    {
        return [];
    }

    /**
     * Render an email template for the current locale and send it immediately
     * (synchronously, within the current request).
     *
     * @param  array<string, string|int|float>  $variables
     *
     * @throws RuntimeException when the template is missing or inactive.
     */
    public function sendTemplate(string $templateKey, string $toAddress, array $variables = [], ?Circle $circle = null): void
    {
        Mail::to($toAddress)->send(
            $this->buildMailable($templateKey, $variables, $circle)
        );
    }

    /**
     * Render an email template for the current locale and push it onto the
     * queue for asynchronous delivery.
     *
     * @param  array<string, string|int|float>  $variables
     *
     * @throws RuntimeException when the template is missing or inactive.
     */
    public function queueTemplate(string $templateKey, string $toAddress, array $variables = [], ?Circle $circle = null): void
    {
        Mail::to($toAddress)->queue(
            $this->buildMailable($templateKey, $variables, $circle)
        );
    }

    /**
     * Resolve, validate and render a template into a ready-to-dispatch mailable.
     *
     * Variables are substituted into the subject and body using the
     * {{ variable_name }} placeholder syntax. The optional $circle is
     * reserved for future circle-scoped context (e.g. per-circle sender).
     *
     * @param  array<string, string|int|float>  $variables
     *
     * @throws RuntimeException when the template is missing or inactive.
     */
    protected function buildMailable(string $templateKey, array $variables, ?Circle $circle): TemplateMailable
    {
        $template = EmailTemplate::getByKey($templateKey);

        if (! $template) {
            throw new RuntimeException("Email template [{$templateKey}] was not found.");
        }

        if (! $template->is_active) {
            throw new RuntimeException("Email template [{$templateKey}] is not active.");
        }

        $locale = app()->getLocale();

        // Build a {{ variable_name }} => value map for strtr() substitution.
        $replacements = [];
        foreach ($variables as $name => $value) {
            $replacements['{{ '.$name.' }}'] = (string) $value;
        }

        $subject = strtr((string) $template->getTranslation('subject', $locale), $replacements);
        $body = strtr((string) $template->getTranslation('body', $locale), $replacements);

        return new TemplateMailable(
            subject: $subject,
            body: $body,
            isHtml: (bool) $template->is_html,
        );
    }
}
