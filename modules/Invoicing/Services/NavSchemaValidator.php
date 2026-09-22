<?php

namespace Modules\Invoicing\Services;

use DOMDocument;
use NavOnlineInvoice\Config;

/**
 * Offline ellenőrzés a NAV hivatalos Online Számla 3.0 XSD-jével
 * (a pzs/nav-online-invoice csomagban szállított sémafájlok).
 * Hálózat és NAV-hozzáférés nélkül is futtatható, CI-ban is.
 */
class NavSchemaValidator
{
    /** @return list<string> üres lista = érvényes */
    public function errors(string $xml): array
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $valid = $doc->loadXML($xml) && $doc->schemaValidate(Config::getDataXsdFilename());

        $errors = $valid ? [] : array_map(
            fn ($e) => trim($e->message)." (sor: {$e->line})",
            libxml_get_errors(),
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return array_values(array_unique($errors));
    }
}
