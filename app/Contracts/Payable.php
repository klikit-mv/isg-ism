<?php

namespace App\Contracts;

/**
 * A billable row (class fee, annual fee or purchase) that payments settle.
 */
interface Payable
{
    /**
     * Short type key used in URLs and forms: class_fee, annual_fee or purchase.
     */
    public function payableTypeKey(): string;

    /**
     * Total amount due, as a decimal string.
     */
    public function amountDue(): string;

    public function payableStudentId(): ?int;

    public function payableDescription(): string;
}
