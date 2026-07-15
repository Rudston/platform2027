<?php

namespace App\Contracts\Circles;

/**
 * Marks a circleable that ships with a default set of services, attached (in
 * order) when its circle is created. Checked via `instanceof` in
 * Circle::booted() and the circles:backfill-services command.
 *
 * Every community model already has a defaultServices() method from the
 * HasCircle trait (returning []), so this interface — not method_exists — is
 * the meaningful signal that a circleable opts into default services.
 */
interface HasDefaultServices
{
    /**
     * Service keys to attach by default, in tab/attachment order.
     *
     * @return list<string>
     */
    public function defaultServices(): array;
}
