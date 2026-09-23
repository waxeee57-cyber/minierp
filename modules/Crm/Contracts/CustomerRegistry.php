<?php

namespace Modules\Crm\Contracts;

/**
 * Író felület az ügyfélkörhöz külső forrásokból (pl. webshop-rendelés).
 * Szándékosan külön a csak olvasó CustomerDirectory-tól: az AI-asszisztens
 * csak azt kapja meg, ezt nem (a modulhatár-teszt ellenőrzi).
 */
interface CustomerRegistry
{
    /**
     * E-mail-cím alapján megkeresi az ügyfelet, vagy létrehozza. Meglévő
     * ügyfél adatait nem írja felül: a CRM-ben rögzített adat az irányadó.
     *
     * @param  array{name:string, email:string, company?:?string, phone?:?string, tax_number?:?string, postal_code?:?string, city?:?string, address?:?string}  $data
     * @return int az ügyfél azonosítója
     */
    public function findOrCreateByEmail(array $data): int;
}
