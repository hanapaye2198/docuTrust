<?php

namespace App\Http\Middleware;

use App\Services\DedicatedVirtualGateway;
use Closure;
use Illuminate\Http\Request;

/**
 * Virtual Gateway Middleware
 * 
 * Routes PKI requests through the dedicated virtual gateway for:
 * - Network isolation
 * - IP allowlisting
 * - mTLS verification
 * - Rate limiting
 * - Audit logging
 *
 * CSC Requirement: "must have a dedicated Virtual Gateway (VGW) that handles
 * all incoming requests. Central control with a VGW and separation of virtual
 * networks maximizes the security."
 */
class VirtualGateway
{
    public function __construct(private readonly DedicatedVirtualGateway $vgw) {}

    public function handle(Request $request, Closure $next)
    {
        return $this->vgw->handle($request, $next);
    }
}
