import { getFieldConfig } from './field-types';

function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
}

function estimateMaxCharacters(width, fontSize) {
    return Math.max(4, Math.floor(width / Math.max(5.5, fontSize * 0.58)));
}

function truncateText(value, width, fontSize) {
    const text = String(value || '').trim();
    const maxCharacters = estimateMaxCharacters(width, fontSize);

    if (text.length <= maxCharacters) {
        return text;
    }

    return `${text.slice(0, Math.max(1, maxCharacters - 1)).trimEnd()}…`;
}

function createBaseFrame(fabric, width, height) {
    return new fabric.Rect({
        width,
        height,
        fill: 'rgba(255,255,255,0.001)',
        stroke: 'transparent',
        strokeWidth: 0,
        rx: 8,
        ry: 8,
    });
}

function createSignatureVisuals(fabric, config, width, height, signerName) {
    const innerInset = Math.max(5, height * 0.14);
    const accentWidth = clamp(width * 0.07, 10, 18);
    const labelFontSize = clamp(height * 0.28, 11, 16);
    const signerFontSize = clamp(height * 0.18, 8, 11);
    const bottomGuideY = height - Math.max(7, height * 0.14);
    const alignment = config.signatureAlignment || 'center';
    const textInset = Math.max(10, width * 0.05);
    const leftLabelLeft = accentWidth + textInset;
    const rightLabelRight = width - accentWidth - textInset;
    const centerLabelLeft = Math.max(innerInset, width * 0.16);
    const centerLabelWidth = Math.max(24, width - (centerLabelLeft * 2));
    const leftUsableWidth = Math.max(24, width - leftLabelLeft - innerInset);
    const rightUsableWidth = Math.max(24, rightLabelRight - innerInset);

    const nodes = [
        new fabric.Rect({
            width,
            height,
            fill: config.fill,
            stroke: config.stroke,
            strokeWidth: 1.5,
            rx: 8,
            ry: 8,
        }),
    ];

    if (alignment === 'right') {
        nodes.push(
            new fabric.Rect({
                width: accentWidth,
                height,
                fill: config.accent,
                rx: 8,
                ry: 8,
                left: width - accentWidth,
                top: 0,
                originX: 'left',
                originY: 'top',
            }),
        );
        nodes.push(
            new fabric.Text(truncateText(config.canvasLabel || config.label, rightUsableWidth, labelFontSize), {
                fontSize: labelFontSize,
                fill: config.text,
                fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
                fontWeight: 700,
                originX: 'right',
                originY: 'top',
                left: rightLabelRight,
                top: innerInset,
                textAlign: 'right',
            }),
        );
        nodes.push(
            new fabric.Text(truncateText(signerName, rightUsableWidth, signerFontSize), {
                fontSize: signerFontSize,
                fill: '#64748b',
                fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
                fontWeight: 500,
                originX: 'right',
                originY: 'top',
                left: rightLabelRight,
                top: Math.min(bottomGuideY - signerFontSize - 3, innerInset + labelFontSize + 3),
                textAlign: 'right',
            }),
        );
        nodes.push(
            new fabric.Line([innerInset, bottomGuideY, rightLabelRight, bottomGuideY], {
                stroke: config.stroke,
                strokeWidth: 1,
                selectable: false,
                evented: false,
                opacity: 0.65,
            }),
        );

        return nodes;
    }

    if (alignment === 'center') {
        const topBandHeight = clamp(height * 0.16, 5, 9);
        const guideWidth = Math.max(width * 0.58, 40);
        const guideLeft = (width - guideWidth) / 2;

        nodes.push(
            new fabric.Rect({
                width: Math.max(width * 0.42, 44),
                height: topBandHeight,
                fill: config.accent,
                rx: 999,
                ry: 999,
                left: (width - Math.max(width * 0.42, 44)) / 2,
                top: innerInset * 0.6,
                originX: 'left',
                originY: 'top',
            }),
        );
        nodes.push(
            new fabric.Text(truncateText(config.canvasLabel || config.label, centerLabelWidth, labelFontSize), {
                fontSize: labelFontSize,
                fill: config.text,
                fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
                fontWeight: 700,
                originX: 'center',
                originY: 'top',
                left: width / 2,
                top: innerInset + 3,
                textAlign: 'center',
            }),
        );
        nodes.push(
            new fabric.Text(truncateText(signerName, centerLabelWidth, signerFontSize), {
                fontSize: signerFontSize,
                fill: '#64748b',
                fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
                fontWeight: 500,
                originX: 'center',
                originY: 'top',
                left: width / 2,
                top: Math.min(bottomGuideY - signerFontSize - 3, innerInset + labelFontSize + 5),
                textAlign: 'center',
            }),
        );
        nodes.push(
            new fabric.Line([guideLeft, bottomGuideY, guideLeft + guideWidth, bottomGuideY], {
                stroke: config.stroke,
                strokeWidth: 1,
                selectable: false,
                evented: false,
                opacity: 0.65,
            }),
        );

        return nodes;
    }

    return [
        ...nodes,
        new fabric.Rect({
            width: accentWidth,
            height,
            fill: config.accent,
            rx: 8,
            ry: 8,
            left: 0,
            top: 0,
            originX: 'left',
            originY: 'top',
        }),
        new fabric.Text(truncateText(config.canvasLabel || config.label, leftUsableWidth, labelFontSize), {
            fontSize: labelFontSize,
            fill: config.text,
            fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
            fontWeight: 700,
            originX: 'left',
            originY: 'top',
            left: leftLabelLeft,
            top: innerInset,
        }),
        new fabric.Text(truncateText(signerName, leftUsableWidth, signerFontSize), {
            fontSize: signerFontSize,
            fill: '#64748b',
            fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
            fontWeight: 500,
            originX: 'left',
            originY: 'top',
            left: leftLabelLeft,
            top: Math.min(bottomGuideY - signerFontSize - 3, innerInset + labelFontSize + 3),
        }),
        new fabric.Line([leftLabelLeft, bottomGuideY, width - innerInset, bottomGuideY], {
            stroke: config.stroke,
            strokeWidth: 1,
            selectable: false,
            evented: false,
            opacity: 0.65,
        }),
    ];
}

function createInputVisuals(fabric, config, width, height, signerName) {
    const innerInset = Math.max(7, height * 0.16);
    const accentWidth = clamp(width * 0.055, 8, 14);
    const labelLeft = accentWidth + Math.max(10, width * 0.05);
    const labelFontSize = clamp(height * 0.28, 10, 15);
    const signerFontSize = clamp(height * 0.18, 8, 10);
    const usableWidth = Math.max(24, width - labelLeft - innerInset);
    const bottomLineY = height - Math.max(7, height * 0.18);
    const subtitle = width >= 110 ? truncateText(signerName, usableWidth, signerFontSize) : '';
    const nodes = [
        new fabric.Rect({
            width,
            height,
            fill: config.fill,
            stroke: config.stroke,
            strokeWidth: 1.5,
            rx: 8,
            ry: 8,
        }),
        new fabric.Rect({
            width: accentWidth,
            height,
            fill: config.accent,
            rx: 8,
            ry: 8,
            left: 0,
            top: 0,
            originX: 'left',
            originY: 'top',
        }),
        new fabric.Text(truncateText(config.canvasLabel || config.label, usableWidth, labelFontSize), {
            fontSize: labelFontSize,
            fill: config.text,
            fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
            fontWeight: 700,
            originX: 'left',
            originY: 'top',
            left: labelLeft,
            top: innerInset,
        }),
        new fabric.Line([labelLeft, bottomLineY, width - innerInset, bottomLineY], {
            stroke: config.stroke,
            strokeWidth: 1,
            selectable: false,
            evented: false,
            opacity: 0.4,
        }),
    ];

    if (subtitle) {
        nodes.push(
            new fabric.Text(subtitle, {
                fontSize: signerFontSize,
                fill: '#64748b',
                fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
                fontWeight: 500,
                originX: 'left',
                originY: 'top',
                left: labelLeft,
                top: Math.min(bottomLineY - signerFontSize - 2, innerInset + labelFontSize + 2),
            }),
        );
    }

    return nodes;
}

function createToggleVisuals(fabric, config, width, height) {
    const innerInset = Math.max(7, height * 0.18);
    const controlSize = clamp(height * 0.5, 14, 20);
    const controlTop = (height - controlSize) / 2;
    const labelLeft = innerInset + controlSize + Math.max(7, width * 0.05);
    const labelFontSize = clamp(height * 0.3, 10, 14);
    const usableWidth = Math.max(18, width - labelLeft - innerInset);
    const nodes = [
        new fabric.Rect({
            width,
            height,
            fill: config.fill,
            stroke: config.stroke,
            strokeWidth: 1.25,
            rx: 8,
            ry: 8,
        }),
    ];

    if (config.control === 'circle') {
        nodes.push(
            new fabric.Circle({
                radius: controlSize / 2,
                left: innerInset,
                top: controlTop,
                fill: '#ffffff',
                stroke: config.stroke,
                strokeWidth: 1.5,
                originX: 'left',
                originY: 'top',
            }),
        );
    } else {
        nodes.push(
            new fabric.Rect({
                width: controlSize,
                height: controlSize,
                left: innerInset,
                top: controlTop,
                fill: '#ffffff',
                stroke: config.stroke,
                strokeWidth: 1.5,
                rx: 4,
                ry: 4,
                originX: 'left',
                originY: 'top',
            }),
        );
    }

    nodes.push(
        new fabric.Text(truncateText(config.canvasLabel || config.label, usableWidth, labelFontSize), {
            fontSize: labelFontSize,
            fill: config.text,
            fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
            fontWeight: 700,
            originX: 'left',
            originY: 'center',
            left: labelLeft,
            top: height / 2,
        }),
    );

    return nodes;
}

function objectFrame(target) {
    const left = Number(target?.left) || 0;
    const top = Number(target?.top) || 0;
    const width = Number(target?.getScaledWidth?.()) || 0;
    const height = Number(target?.getScaledHeight?.()) || 0;

    return { left, top, width, height };
}

export function createFieldGroup({
    fabric,
    fabricCanvas,
    type,
    signerId,
    signerName,
    position,
    pageNumber,
    clientFieldId,
}) {
    const w = fabricCanvas.getWidth();
    const h = fabricCanvas.getHeight();
    const left = position.x * w;
    const top = position.y * h;
    const width = position.width * w;
    const height = position.height * h;
    const config = getFieldConfig(type);
    const visuals =
        config.kind === 'toggle'
            ? createToggleVisuals(fabric, config, width, height)
            : config.kind === 'input'
                ? createInputVisuals(fabric, config, width, height, signerName)
                : createSignatureVisuals(fabric, config, width, height, signerName);

    const group = new fabric.Group([createBaseFrame(fabric, width, height), ...visuals], {
        left,
        top,
        subTargetCheck: true,
        originX: 'left',
        originY: 'top',
    });

    group.fieldType = type;
    group.signerId = signerId;
    group.pageNumber = pageNumber;
    group.clientFieldId = clientFieldId;
    group.hasControls = true;
    group.hasBorders = true;
    group.padding = 1;
    group.objectCaching = false;
    group.cornerColor = config.stroke;
    group.cornerStrokeColor = '#ffffff';
    group.cornerStyle = 'circle';
    group.cornerSize = Math.max(10, Math.min(14, width * 0.08));
    group.transparentCorners = false;
    group.borderColor = config.stroke;
    group.borderScaleFactor = 1.5;
    group.borderDashArray = [6, 4];
    group.hoverCursor = 'move';
    group.moveCursor = 'move';
    group.lockRotation = false;
    group.setControlsVisibility({ mtr: true });

    // Apply rotation if provided
    if (position.angle) {
        group.angle = Number(position.angle) || 0;
        group.setCoords();
    }

    return group;
}

export function normalizedPositionFromObject(target, fabricCanvas) {
    if (!target || !fabricCanvas) {
        return null;
    }

    const bound = objectFrame(target);
    const canvasWidth = fabricCanvas.getWidth();
    const canvasHeight = fabricCanvas.getHeight();

    return {
        x: bound.left / canvasWidth,
        y: bound.top / canvasHeight,
        width: bound.width / canvasWidth,
        height: bound.height / canvasHeight,
        angle: Number(target.angle) || 0,
    };
}

export function serializeCanvasFields(fabricCanvas) {
    const out = [];
    const w = fabricCanvas.getWidth();
    const h = fabricCanvas.getHeight();

    fabricCanvas.getObjects().forEach((obj) => {
        if (!obj.fieldType) {
            return;
        }

        const br = objectFrame(obj);
        out.push({
            client_id: obj.clientFieldId,
            signer_id: obj.signerId,
            type: obj.fieldType,
            position_data: {
                x: br.left / w,
                y: br.top / h,
                width: br.width / w,
                height: br.height / h,
                angle: Number(obj.angle) || 0,
            },
        });
    });

    return out;
}

export function restoreSelectionByClientId(fabricCanvas, selectedFieldClientId) {
    if (!selectedFieldClientId || !fabricCanvas) {
        return;
    }

    const target = fabricCanvas.getObjects().find((obj) => obj.clientFieldId === selectedFieldClientId);
    if (target) {
        fabricCanvas.setActiveObject(target);
        fabricCanvas.requestRenderAll();
    }
}
