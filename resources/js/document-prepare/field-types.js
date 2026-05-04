export const FIELD_TYPES = {
    signature: { label: 'Signature', canvasLabel: 'Signature', kind: 'signature', fill: 'rgba(59, 130, 246, 0.10)', stroke: '#2563eb', text: '#1d4ed8', width: 0.26, height: 0.058, minWidth: 0.18, minHeight: 0.04, accent: '#2563eb' },
    signature_left: { label: 'Signature (Left aligned)', canvasLabel: 'Signature', kind: 'signature', fill: 'rgba(20, 184, 166, 0.10)', stroke: '#0f766e', text: '#115e59', width: 0.26, height: 0.058, minWidth: 0.18, minHeight: 0.04, accent: '#0f766e' },
    signature_right: { label: 'Signature (Right aligned)', canvasLabel: 'Signature', kind: 'signature', fill: 'rgba(14, 165, 233, 0.10)', stroke: '#0369a1', text: '#075985', width: 0.26, height: 0.058, minWidth: 0.18, minHeight: 0.04, accent: '#0369a1' },
    text: { label: 'Text Field', canvasLabel: 'Text field', kind: 'input', fill: 'rgba(234, 179, 8, 0.12)', stroke: '#ca8a04', text: '#a16207', width: 0.24, height: 0.05, minWidth: 0.16, minHeight: 0.034, accent: '#ca8a04' },
    checkbox: { label: 'Checkbox', canvasLabel: 'Checkbox', kind: 'toggle', control: 'square', fill: 'rgba(56, 189, 248, 0.12)', stroke: '#0284c7', text: '#0369a1', width: 0.14, height: 0.04, minWidth: 0.08, minHeight: 0.028, accent: '#0284c7' },
    radio: { label: 'Radio Button', canvasLabel: 'Radio', kind: 'toggle', control: 'circle', fill: 'rgba(99, 102, 241, 0.10)', stroke: '#4f46e5', text: '#4338ca', width: 0.14, height: 0.04, minWidth: 0.08, minHeight: 0.028, accent: '#4f46e5' },
    date: { label: 'Date Signed', canvasLabel: 'Date', kind: 'input', fill: 'rgba(139, 92, 246, 0.10)', stroke: '#6d28d9', text: '#5b21b6', width: 0.2, height: 0.05, minWidth: 0.14, minHeight: 0.034, accent: '#6d28d9' },
    email: { label: 'Email', canvasLabel: 'Email', kind: 'input', fill: 'rgba(244, 63, 94, 0.09)', stroke: '#be123c', text: '#9f1239', width: 0.24, height: 0.05, minWidth: 0.18, minHeight: 0.034, accent: '#be123c' },
    name: { label: 'Name', canvasLabel: 'Name', kind: 'input', fill: 'rgba(34, 197, 94, 0.12)', stroke: '#15803d', text: '#15803d', width: 0.2, height: 0.05, minWidth: 0.14, minHeight: 0.034, accent: '#15803d' },
    initials: { label: 'Initials', canvasLabel: 'Initials', kind: 'input', fill: 'rgba(217, 70, 239, 0.09)', stroke: '#a21caf', text: '#86198f', width: 0.14, height: 0.05, minWidth: 0.1, minHeight: 0.034, accent: '#a21caf' },
};

export function getFieldConfig(type) {
    return FIELD_TYPES[type] || FIELD_TYPES.signature;
}
