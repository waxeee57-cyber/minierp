<?php

namespace Modules\Invoicing\Services;

use Carbon\CarbonInterface;
use Modules\Invoicing\Data\TaxNumber;
use XMLWriter;

/**
 * NAV Online Számla 3.0 InvoiceData XML előállítása.
 *
 * - Belföldi cégnél adószámmal, névvel, címmel (DOMESTIC).
 * - Magánszemélynél a 3.0-s szabály szerint név és cím NÉLKÜL (PRIVATE_PERSON).
 * A kimenetet a hivatalos XSD-vel ellenőrizzük (NavSchemaValidator), beküldés előtt.
 */
class NavInvoiceXmlBuilder
{
    private const NS_DATA = 'http://schemas.nav.gov.hu/OSA/3.0/data';

    private const NS_BASE = 'http://schemas.nav.gov.hu/OSA/3.0/base';

    private const NS_COMMON = 'http://schemas.nav.gov.hu/NTCA/1.0/common';

    /**
     * @param  array{name:string, tax_number:string, postal_code:string, city:string, address:string, bank_account:string}  $seller
     * @param  array{name:string, tax_number:?string, postal_code:?string, city:?string, address:?string}  $buyer
     * @param  array{lines:list<array>, net:int, vat:int, gross:int, rate:float}  $totals
     */
    public function build(
        string $number,
        CarbonInterface $issueDate,
        CarbonInterface $deliveryDate,
        CarbonInterface $paymentDue,
        array $seller,
        array $buyer,
        array $totals,
    ): string {
        $x = new XMLWriter;
        $x->openMemory();
        $x->setIndent(true);
        $x->startDocument('1.0', 'UTF-8');

        $x->startElementNs(null, 'InvoiceData', self::NS_DATA);
        $x->writeAttribute('xmlns:common', self::NS_COMMON);
        $x->writeAttribute('xmlns:base', self::NS_BASE);

        $x->writeElement('invoiceNumber', $number);
        $x->writeElement('invoiceIssueDate', $issueDate->toDateString());
        $x->writeElement('completenessIndicator', 'false');

        $x->startElement('invoiceMain');
        $x->startElement('invoice');

        $x->startElement('invoiceHead');
        $this->supplier($x, $seller);
        $this->customer($x, $buyer);
        $x->startElement('invoiceDetail');
        $x->writeElement('invoiceCategory', 'NORMAL');
        $x->writeElement('invoiceDeliveryDate', $deliveryDate->toDateString());
        $x->writeElement('currencyCode', 'HUF');
        $x->writeElement('exchangeRate', '1');
        $x->writeElement('paymentMethod', 'TRANSFER');
        $x->writeElement('paymentDate', $paymentDue->toDateString());
        $x->writeElement('invoiceAppearance', 'ELECTRONIC');
        $x->endElement(); // invoiceDetail
        $x->endElement(); // invoiceHead

        $x->startElement('invoiceLines');
        $x->writeElement('mergedItemIndicator', 'false');
        foreach ($totals['lines'] as $line) {
            $this->line($x, $line, $totals['rate']);
        }
        $x->endElement();

        $this->summary($x, $totals);

        $x->endElement(); // invoice
        $x->endElement(); // invoiceMain
        $x->endElement(); // InvoiceData
        $x->endDocument();

        return $x->outputMemory();
    }

    private function supplier(XMLWriter $x, array $seller): void
    {
        $x->startElement('supplierInfo');
        $this->taxNumber($x, 'supplierTaxNumber', TaxNumber::parse($seller['tax_number']));
        $x->writeElement('supplierName', $seller['name']);
        $this->address($x, 'supplierAddress', $seller['postal_code'], $seller['city'], $seller['address']);
        $x->writeElement('supplierBankAccountNumber', $seller['bank_account']);
        $x->endElement();
    }

    private function customer(XMLWriter $x, array $buyer): void
    {
        $x->startElement('customerInfo');

        if (filled($buyer['tax_number'])) {
            $x->writeElement('customerVatStatus', 'DOMESTIC');
            $x->startElement('customerVatData');
            $this->taxNumber($x, 'customerTaxNumber', TaxNumber::parse($buyer['tax_number']));
            $x->endElement();
            $x->writeElement('customerName', $buyer['name']);
            $this->address($x, 'customerAddress', $buyer['postal_code'], $buyer['city'], $buyer['address']);
        } else {
            // Online Számla 3.0: magánszemély vevő adatai nem kerülnek a NAV-hoz.
            $x->writeElement('customerVatStatus', 'PRIVATE_PERSON');
        }

        $x->endElement();
    }

    private function taxNumber(XMLWriter $x, string $element, TaxNumber $tax): void
    {
        $x->startElement($element);
        $x->writeElement('base:taxpayerId', $tax->taxpayerId);
        $x->writeElement('base:vatCode', $tax->vatCode);
        $x->writeElement('base:countyCode', $tax->countyCode);
        $x->endElement();
    }

    private function address(XMLWriter $x, string $element, ?string $postalCode, ?string $city, ?string $detail): void
    {
        $x->startElement($element);
        $x->startElement('base:simpleAddress');
        $x->writeElement('base:countryCode', 'HU');
        $x->writeElement('base:postalCode', $postalCode ?: '0000');
        $x->writeElement('base:city', $city ?: 'N/A');
        $x->writeElement('base:additionalAddressDetail', $detail ?: 'N/A');
        $x->endElement();
        $x->endElement();
    }

    private function line(XMLWriter $x, array $line, float $rate): void
    {
        $x->startElement('line');
        $x->writeElement('lineNumber', (string) $line['line_number']);
        $x->startElement('productCodes');
        $x->startElement('productCode');
        $x->writeElement('productCodeCategory', 'OWN');
        // A NAV OWN termékkódja csak [A-Z0-9]{2,30} lehet: az IT-1001 cikkszámból IT1001 lesz.
        $x->writeElement('productCodeValue', substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($line['sku'])), 0, 30));
        $x->endElement();
        $x->endElement();
        $x->writeElement('lineExpressionIndicator', 'true');
        $x->writeElement('lineNatureIndicator', 'PRODUCT');
        $x->writeElement('lineDescription', $line['name']);
        $x->writeElement('quantity', (string) $line['quantity']);
        $x->writeElement('unitOfMeasure', 'PIECE');
        $x->writeElement('unitPrice', (string) $line['unit_price']);
        $x->writeElement('unitPriceHUF', (string) $line['unit_price']);
        $x->startElement('lineAmountsNormal');
        $x->startElement('lineNetAmountData');
        $x->writeElement('lineNetAmount', (string) $line['net']);
        $x->writeElement('lineNetAmountHUF', (string) $line['net']);
        $x->endElement();
        $x->startElement('lineVatRate');
        $x->writeElement('vatPercentage', (string) $rate);
        $x->endElement();
        $x->startElement('lineVatData');
        $x->writeElement('lineVatAmount', (string) $line['vat']);
        $x->writeElement('lineVatAmountHUF', (string) $line['vat']);
        $x->endElement();
        $x->startElement('lineGrossAmountData');
        $x->writeElement('lineGrossAmountNormal', (string) $line['gross']);
        $x->writeElement('lineGrossAmountNormalHUF', (string) $line['gross']);
        $x->endElement();
        $x->endElement(); // lineAmountsNormal
        $x->endElement(); // line
    }

    private function summary(XMLWriter $x, array $t): void
    {
        $x->startElement('invoiceSummary');
        $x->startElement('summaryNormal');
        $x->startElement('summaryByVatRate');
        $x->startElement('vatRate');
        $x->writeElement('vatPercentage', (string) $t['rate']);
        $x->endElement();
        $x->startElement('vatRateNetData');
        $x->writeElement('vatRateNetAmount', (string) $t['net']);
        $x->writeElement('vatRateNetAmountHUF', (string) $t['net']);
        $x->endElement();
        $x->startElement('vatRateVatData');
        $x->writeElement('vatRateVatAmount', (string) $t['vat']);
        $x->writeElement('vatRateVatAmountHUF', (string) $t['vat']);
        $x->endElement();
        $x->endElement(); // summaryByVatRate
        $x->writeElement('invoiceNetAmount', (string) $t['net']);
        $x->writeElement('invoiceNetAmountHUF', (string) $t['net']);
        $x->writeElement('invoiceVatAmount', (string) $t['vat']);
        $x->writeElement('invoiceVatAmountHUF', (string) $t['vat']);
        $x->endElement(); // summaryNormal
        $x->startElement('summaryGrossData');
        $x->writeElement('invoiceGrossAmount', (string) $t['gross']);
        $x->writeElement('invoiceGrossAmountHUF', (string) $t['gross']);
        $x->endElement();
        $x->endElement(); // invoiceSummary
    }
}
