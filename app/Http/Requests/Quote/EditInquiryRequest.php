<?php

namespace App\Http\Requests\Quote;

/**
 * The reference in `quote/{reference}/edit`, held to the same shape as the
 * details page's: a malformed reference looks like an unknown one (404).
 */
class EditInquiryRequest extends ShowInquiryDetailsRequest {}
