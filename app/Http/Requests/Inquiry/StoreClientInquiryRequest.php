<?php

namespace App\Http\Requests\Inquiry;

/**
 * The Inquire page's answers when a client sends their own inquiry: the base
 * request's rules, with the address picked from the client's own address book
 * (`projects.*.addressId`) rather than entered. The inquiry keeps a copy, so
 * a later change to the book never changes what was sent.
 */
class StoreClientInquiryRequest extends StoreInquiryRequest
{
    protected function addressSource(): string
    {
        return self::ADDRESS_FROM_BOOK;
    }
}
