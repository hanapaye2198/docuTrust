<?php

namespace App\Support;

use setasign\Fpdi\Fpdi;

/**
 * Extends Fpdi to expose PDF content stream rotation via the
 * graphics state operators (q/Q/cm).
 */
class RotatableFpdi extends Fpdi
{
    /**
     * Save the current graphics state and apply a rotation
     * around the given center point (in user units).
     */
    public function rotateAround(float $angleDegrees, float $centerX, float $centerY): void
    {
        if (abs($angleDegrees) < 0.01) {
            return;
        }

        // Save graphics state
        $this->_out('q');

        // Convert center to PDF coordinate space
        $cx = $centerX * $this->k;
        $cy = ($this->h - $centerY) * $this->k;

        // Convert angle to radians (negative for clockwise in PDF space)
        $rad = deg2rad(-$angleDegrees);
        $cos = cos($rad);
        $sin = sin($rad);

        // Build the combined transformation matrix:
        // Translate to center → rotate → translate back
        $tx = $cx - ($cos * $cx) + ($sin * $cy);
        $ty = $cy - ($sin * $cx) - ($cos * $cy);

        $this->_out(sprintf(
            '%.5f %.5f %.5f %.5f %.5f %.5f cm',
            $cos,
            $sin,
            -$sin,
            $cos,
            $tx,
            $ty,
        ));
    }

    /**
     * Restore the graphics state (undo the last rotation).
     */
    public function endRotation(): void
    {
        $this->_out('Q');
    }
}
