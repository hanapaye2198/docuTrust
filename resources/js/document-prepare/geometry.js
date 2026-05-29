import { getFieldConfig } from './field-types';

function objectFrame(target) {
    return {
        left: Number(target?.left) || 0,
        top: Number(target?.top) || 0,
        width: Number(target?.getScaledWidth?.()) || 0,
        height: Number(target?.getScaledHeight?.()) || 0,
    };
}

/**
 * Returns the axis-aligned bounding box of the object, accounting for rotation.
 * Calculates the rotated corners manually to avoid Fabric.js viewport transform issues.
 */
function objectBoundingFrame(target) {
    const left = Number(target?.left) || 0;
    const top = Number(target?.top) || 0;
    const width = Number(target?.getScaledWidth?.()) || 0;
    const height = Number(target?.getScaledHeight?.()) || 0;
    const angle = Number(target?.angle) || 0;

    if (Math.abs(angle) < 0.01) {
        return { left, top, width, height };
    }

    // Calculate the four corners of the rotated rectangle
    const rad = (angle * Math.PI) / 180;
    const cos = Math.cos(rad);
    const sin = Math.sin(rad);

    // Corners relative to top-left origin (Fabric default origin)
    const corners = [
        { x: 0, y: 0 },
        { x: width, y: 0 },
        { x: width, y: height },
        { x: 0, y: height },
    ];

    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    for (const corner of corners) {
        const rx = corner.x * cos - corner.y * sin + left;
        const ry = corner.x * sin + corner.y * cos + top;
        minX = Math.min(minX, rx);
        minY = Math.min(minY, ry);
        maxX = Math.max(maxX, rx);
        maxY = Math.max(maxY, ry);
    }

    return {
        left: minX,
        top: minY,
        width: maxX - minX,
        height: maxY - minY,
    };
}

export function resolveRenderScale(page, panelWidth) {
    const baseViewport = page.getViewport({ scale: 1 });
    const shellPadding = 24;
    const availableWidth = Math.max(320, panelWidth - shellPadding);

    if (baseViewport.width <= 0) {
        return 1;
    }

    const fitScale = availableWidth / baseViewport.width;
    return Math.min(2, Math.max(0.5, fitScale));
}

export function resolveVisiblePosition({ width, height, pdfPanel, fabricEl }) {
    const fallback = {
        x: Math.max(0.01, Math.min(0.99 - width, 0.08)),
        y: Math.max(0.01, Math.min(0.99 - height, 0.12)),
    };

    if (!pdfPanel || !fabricEl) {
        return fallback;
    }

    const panelRect = pdfPanel.getBoundingClientRect();
    const canvasRect = fabricEl.getBoundingClientRect();
    const visibleLeft = Math.max(panelRect.left, canvasRect.left);
    const visibleTop = Math.max(panelRect.top, canvasRect.top);
    const visibleRight = Math.min(panelRect.right, canvasRect.right);
    const visibleBottom = Math.min(panelRect.bottom, canvasRect.bottom);

    if (visibleRight <= visibleLeft || visibleBottom <= visibleTop) {
        return fallback;
    }

    const centerX = visibleLeft + (visibleRight - visibleLeft) / 2;
    const centerY = visibleTop + (visibleBottom - visibleTop) / 2;
    const normalizedX = (centerX - canvasRect.left) / canvasRect.width;
    const normalizedY = (centerY - canvasRect.top) / canvasRect.height;

    return {
        x: Math.max(0.01, Math.min(0.99 - width, normalizedX - width / 2)),
        y: Math.max(0.01, Math.min(0.99 - height, normalizedY - height / 2)),
    };
}

export function normalizedPositionFromClientPoint({ clientX, clientY, width, height, fabricEl, pdfPanel }) {
    const fallback = resolveVisiblePosition({ width, height, pdfPanel, fabricEl });

    if (!fabricEl) {
        return fallback;
    }

    const rect = fabricEl.getBoundingClientRect();
    if (rect.width <= 0 || rect.height <= 0) {
        return fallback;
    }

    const x = (clientX - rect.left) / rect.width;
    const y = (clientY - rect.top) / rect.height;

    return {
        x: Math.max(0.01, Math.min(0.99 - width, x - width / 2)),
        y: Math.max(0.01, Math.min(0.99 - height, y - height / 2)),
    };
}

export function canvasContainsClientPoint(clientX, clientY, fabricEl) {
    if (!fabricEl) {
        return false;
    }

    const rect = fabricEl.getBoundingClientRect();
    return clientX >= rect.left && clientX <= rect.right && clientY >= rect.top && clientY <= rect.bottom;
}

export function keepObjectInsideCanvas(target, fabricCanvas) {
    if (!target || !fabricCanvas) {
        return;
    }

    const bound = objectBoundingFrame(target);
    const canvasWidth = fabricCanvas.getWidth();
    const canvasHeight = fabricCanvas.getHeight();
    let deltaX = 0;
    let deltaY = 0;

    if (bound.left < 0) {
        deltaX = -bound.left;
    } else if (bound.left + bound.width > canvasWidth) {
        deltaX = canvasWidth - (bound.left + bound.width);
    }

    if (bound.top < 0) {
        deltaY = -bound.top;
    } else if (bound.top + bound.height > canvasHeight) {
        deltaY = canvasHeight - (bound.top + bound.height);
    }

    if (deltaX !== 0 || deltaY !== 0) {
        target.left += deltaX;
        target.top += deltaY;
        target.setCoords();
    }
}

export function drawSnapGuide({ fabric, fabricCanvas, currentSnapGuide, orientation, position }) {
    if (!fabricCanvas) {
        return null;
    }

    if (currentSnapGuide) {
        fabricCanvas.remove(currentSnapGuide);
    }

    const width = fabricCanvas.getWidth();
    const height = fabricCanvas.getHeight();
    const nextGuide = new fabric.Line(
        orientation === 'vertical'
            ? [position, 0, position, height]
            : [0, position, width, position],
        {
            stroke: '#14b8a6',
            strokeWidth: 1,
            selectable: false,
            evented: false,
            excludeFromExport: true,
            opacity: 0.9,
        },
    );
    fabricCanvas.add(nextGuide);
    if (typeof nextGuide.sendToBack === 'function') {
        nextGuide.sendToBack();
    } else if (typeof fabricCanvas.sendToBack === 'function') {
        fabricCanvas.sendToBack(nextGuide);
    } else if (typeof fabricCanvas.moveTo === 'function') {
        fabricCanvas.moveTo(nextGuide, 0);
    }

    return nextGuide;
}

export function snapObjectToGuides({ target, fabric, fabricCanvas, currentSnapGuide }) {
    if (!target || !fabricCanvas) {
        return currentSnapGuide;
    }

    const bound = objectFrame(target);
    const canvasWidth = fabricCanvas.getWidth();
    const canvasHeight = fabricCanvas.getHeight();
    const thresholdX = Math.max(6, canvasWidth * 0.008);
    const thresholdY = Math.max(6, canvasHeight * 0.008);
    const leftEdge = bound.left;
    const rightEdge = bound.left + bound.width;
    const topEdge = bound.top;
    const bottomEdge = bound.top + bound.height;
    const centerX = bound.left + bound.width / 2;
    const centerY = bound.top + bound.height / 2;
    let snapApplied = false;
    let nextGuide = currentSnapGuide;

    const verticalTargets = [0, canvasWidth / 2, canvasWidth];
    const horizontalTargets = [0, canvasHeight / 2, canvasHeight];
    const verticalChecks = [
        { value: leftEdge, apply: (delta) => { target.left += delta; } },
        { value: centerX, apply: (delta) => { target.left += delta; } },
        { value: rightEdge, apply: (delta) => { target.left += delta; } },
    ];
    const horizontalChecks = [
        { value: topEdge, apply: (delta) => { target.top += delta; } },
        { value: centerY, apply: (delta) => { target.top += delta; } },
        { value: bottomEdge, apply: (delta) => { target.top += delta; } },
    ];

    for (const targetX of verticalTargets) {
        const match = verticalChecks.find((check) => Math.abs(check.value - targetX) <= thresholdX);
        if (match) {
            match.apply(targetX - match.value);
            nextGuide = drawSnapGuide({ fabric, fabricCanvas, currentSnapGuide: nextGuide, orientation: 'vertical', position: targetX });
            snapApplied = true;
            break;
        }
    }

    if (!snapApplied) {
        for (const targetY of horizontalTargets) {
            const match = horizontalChecks.find((check) => Math.abs(check.value - targetY) <= thresholdY);
            if (match) {
                match.apply(targetY - match.value);
                nextGuide = drawSnapGuide({ fabric, fabricCanvas, currentSnapGuide: nextGuide, orientation: 'horizontal', position: targetY });
                snapApplied = true;
                break;
            }
        }
    }

    if (!snapApplied && nextGuide) {
        fabricCanvas.remove(nextGuide);
        nextGuide = null;
    }

    target.setCoords();
    keepObjectInsideCanvas(target, fabricCanvas);

    return nextGuide;
}

export function enforceObjectMinimumSize(target, fabricCanvas) {
    if (!target || !fabricCanvas) {
        return;
    }

    const canvasWidth = fabricCanvas.getWidth();
    const canvasHeight = fabricCanvas.getHeight();
    const config = getFieldConfig(target.fieldType);
    const minWidth = Math.max(24, canvasWidth * (config.minWidth || 0.06));
    const minHeight = Math.max(18, canvasHeight * (config.minHeight || 0.04));
    const currentWidth = target.getScaledWidth();
    const currentHeight = target.getScaledHeight();

    if (currentWidth < minWidth) {
        target.scaleX = minWidth / target.width;
    }
    if (currentHeight < minHeight) {
        target.scaleY = minHeight / target.height;
    }
    target.setCoords();
}
