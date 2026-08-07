<?php
declare(strict_types=1);
?>
<style>
    .hc-page {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        margin: 0 auto;
        padding: 10px 0 42px;
        box-sizing: border-box;
        overflow-x: hidden;
    }

    .hc-panel {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow: hidden;
        box-sizing: border-box;
        border: 1px solid #dbe5f0;
        border-radius: 18px;
        background: #ffffff;
        box-shadow: 0 15px 40px rgba(15, 35, 65, 0.08);
    }

    .hc-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        width: 100%;
        min-width: 0;
        padding: 22px 24px;
        box-sizing: border-box;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #ffffff, #f7fbff);
    }

    .hc-header > div:first-child {
        min-width: 0;
        flex: 1 1 auto;
    }

    .hc-header > .hc-btn,
    .hc-header > .hc-actions {
        flex: 0 0 auto;
    }

    /*
    |--------------------------------------------------------------------------
    | CONTROLES DEL ENCABEZADO
    |--------------------------------------------------------------------------
    */

    .hc-header-copy {
        min-width: 0;
        flex: 1 1 auto;
    }

    .hc-header-controls {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        width: auto;
        min-width: 0;
        flex: 0 0 auto;
        white-space: nowrap;
    }

    /*
     * Evita que los botones de Ver/Editar ocupen todo el encabezado.
     */
    .hc-header > .hc-actions {
        width: auto;
        min-width: auto;
        justify-content: flex-end;
    }

    .hc-record-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 40px;
        padding: 8px 13px;
        border: 1px solid #dbeafe;
        border-radius: 999px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 13px;
        font-weight: 800;
        white-space: nowrap;
    }

    .hc-header h1 {
        margin: 0 0 6px;
        color: #08264f;
        font-size: 25px;
    }

    .hc-header p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
        line-height: 1.5;
    }

    .hc-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 10px 16px;
        border: 0;
        border-radius: 10px;
        font: inherit;
        font-size: 14px;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
    }

    .hc-btn-primary {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.22);
    }

    .hc-btn-primary:hover {
        background: #1d4ed8;
    }

    .hc-header .hc-btn-primary {
        max-width: 240px;
        white-space: nowrap;
    }

    .hc-primary-text,
    .hc-secondary-text,
    .hc-table td {
        max-width: 100%;
    }

    .hc-table td:nth-child(4),
    .hc-table td:nth-child(5) {
        white-space: normal;
    }

    .hc-btn-secondary {
        background: #e9eef5;
        color: #334155;
    }

    .hc-btn-warning {
        background: #f59e0b;
        color: #ffffff;
    }

    .hc-btn-danger {
        background: #dc2626;
        color: #ffffff;
    }

    .hc-toolbar {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: 14px;
        width: 100%;
        min-width: 0;
        padding: 16px 24px;
        box-sizing: border-box;
        border-bottom: 1px solid #e8eef5;
        background: #fbfdff;
    }

    .hc-search {
        display: grid;
        grid-template-columns: minmax(240px, 1.5fr) minmax(180px, 1fr) auto auto;
        align-items: center;
        gap: 10px;
        width: 100%;
        min-width: 0;
    }

    .hc-search input,
    .hc-search select {
        min-height: 42px;
        padding: 10px 13px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #ffffff;
        color: #0f172a;
        font: inherit;
        outline: none;
    }

    .hc-search input {
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }

    .hc-search input:focus,
    .hc-search select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .hc-count {
        white-space: nowrap;
        color: #475569;
        font-size: 14px;
        font-weight: 700;
    }

    .hc-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        width: 100%;
        min-width: 0;
        padding: 20px 24px 0;
        box-sizing: border-box;
    }

    .hc-stat {
        padding: 17px;
        border: 1px solid #e2e8f0;
        border-radius: 13px;
        background: #f8fafc;
    }

    .hc-stat span {
        display: block;
        margin-bottom: 6px;
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .hc-stat strong {
        color: #0f2747;
        font-size: 25px;
    }

    .hc-alert {
        margin: 20px 27px 0;
        padding: 14px 16px;
        border-radius: 11px;
        font-size: 14px;
        font-weight: 700;
    }

    .hc-alert-success {
        border: 1px solid #bbf7d0;
        background: #f0fdf4;
        color: #166534;
    }

    .hc-alert-error {
        border: 1px solid #fecaca;
        background: #fff1f2;
        color: #b91c1c;
    }

    .hc-content {
        width: 100%;
        min-width: 0;
        padding: 22px 24px 26px;
        box-sizing: border-box;
    }

    .hc-table-wrapper {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: auto;
        overflow-y: hidden;
        border: 1px solid #e2e8f0;
        border-radius: 13px;
        box-sizing: border-box;
        scrollbar-width: thin;
    }

    .hc-table {
        width: 100%;
        min-width: 1000px;
        table-layout: fixed;
        border-collapse: collapse;
    }

    .hc-table th:nth-child(1),
    .hc-table td:nth-child(1) {
        width: 9%;
    }

    .hc-table th:nth-child(2),
    .hc-table td:nth-child(2) {
        width: 9%;
    }

    .hc-table th:nth-child(3),
    .hc-table td:nth-child(3) {
        width: 12%;
    }

    .hc-table th:nth-child(4),
    .hc-table td:nth-child(4) {
        width: 17%;
    }

    .hc-table th:nth-child(5),
    .hc-table td:nth-child(5) {
        width: 13%;
    }

    .hc-table th:nth-child(6),
    .hc-table td:nth-child(6) {
        width: 11%;
    }

    .hc-table th:nth-child(7),
    .hc-table td:nth-child(7) {
        width: 11%;
    }

    .hc-table th:nth-child(8),
    .hc-table td:nth-child(8) {
        width: 18%;
    }

    .hc-table th,
    .hc-table td {
        padding: 12px 11px;
        border-bottom: 1px solid #e8eef5;
        text-align: left;
        vertical-align: middle;
        font-size: 12.5px;
        line-height: 1.45;
        overflow-wrap: anywhere;
        word-break: normal;
    }

    .hc-table th {
        background: #f8fafc;
        color: #334155;
        font-weight: 800;
    }

    .hc-table tbody tr:hover {
        background: #f8fbff;
    }

    .hc-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .hc-primary-text {
        display: block;
        color: #0f2747;
        font-size: 14px;
        font-weight: 800;
    }

    .hc-secondary-text {
        display: block;
        margin-top: 3px;
        color: #64748b;
        font-size: 12px;
    }

    .hc-actions {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 6px;
        width: 100%;
        min-width: 0;
        white-space: nowrap;
    }

    .hc-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 32px;
        padding: 6px 8px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 800;
        text-decoration: none;
        flex: 0 0 auto;
    }

    .hc-action-view {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .hc-action-edit {
        background: #fff7ed;
        color: #c2410c;
    }

    .hc-action-delete {
        background: #fff1f2;
        color: #be123c;
    }

    .hc-empty {
        padding: 48px 20px;
        border: 1px dashed #cbd5e1;
        border-radius: 14px;
        background: #fbfdff;
        text-align: center;
    }

    .hc-empty span {
        display: block;
        margin-bottom: 12px;
        font-size: 40px;
    }

    .hc-empty h2 {
        margin: 0 0 8px;
        color: #0f2747;
        font-size: 21px;
    }

    .hc-empty p {
        margin: 0 0 18px;
        color: #64748b;
    }

    .hc-form {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        padding: 28px;
        box-sizing: border-box;
    }

    /*
    |--------------------------------------------------------------------------
    | FORMULARIO DE EDICIÓN
    |--------------------------------------------------------------------------
    */

    .hc-edit-form {
        width: min(1120px, 100%);
        margin: 0 auto;
        padding: 26px 28px 30px;
    }

    .hc-edit-grid {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        align-items: start;
    }

    .hc-edit-grid .hc-field {
        min-width: 0;
    }

    .hc-edit-grid .hc-field input,
    .hc-edit-grid .hc-field select,
    .hc-edit-grid .hc-field textarea {
        min-width: 0;
        box-sizing: border-box;
    }

    .hc-edit-grid .hc-field textarea {
        min-height: 105px;
    }

    .hc-edit-grid .hc-field-full textarea {
        min-height: 115px;
    }

    .hc-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
    }

    .hc-field {
        display: grid;
        gap: 8px;
    }

    .hc-field-full {
        grid-column: 1 / -1;
    }

    .hc-field label {
        color: #334155;
        font-size: 14px;
        font-weight: 800;
    }

    .hc-field input,
    .hc-field select,
    .hc-field textarea {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #ffffff;
        color: #0f172a;
        font: inherit;
        font-size: 14px;
        outline: none;
    }

    .hc-field input:focus,
    .hc-field select:focus,
    .hc-field textarea:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .hc-field textarea {
        min-height: 110px;
        resize: vertical;
    }

    .hc-help {
        color: #64748b;
        font-size: 12px;
        line-height: 1.45;
    }

    .hc-form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 28px;
        padding-top: 22px;
        border-top: 1px solid #e2e8f0;
    }

    .hc-detail-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .hc-detail {
        padding: 17px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #f8fafc;
    }

    .hc-detail-full {
        grid-column: 1 / -1;
    }

    .hc-detail span {
        display: block;
        margin-bottom: 6px;
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .hc-detail strong,
    .hc-detail p {
        margin: 0;
        color: #172033;
        font-size: 14px;
        line-height: 1.65;
        white-space: pre-wrap;
    }

    @media (max-width: 1250px) {
        .hc-header,
        .hc-toolbar,
        .hc-content,
        .hc-form {
            padding-left: 18px;
            padding-right: 18px;
        }

        .hc-stats {
            padding-left: 18px;
            padding-right: 18px;
        }

        .hc-alert {
            margin-left: 18px;
            margin-right: 18px;
        }

        .hc-table {
            min-width: 980px;
        }

        .hc-table th,
        .hc-table td {
            padding-left: 9px;
            padding-right: 9px;
            font-size: 12px;
        }

        .hc-action {
            padding-left: 7px;
            padding-right: 7px;
            font-size: 10.5px;
        }
    }

    @media (max-width: 950px) {
        .hc-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .hc-toolbar {
            grid-template-columns: 1fr;
        }

        .hc-count {
            justify-self: end;
        }

        .hc-search {
            grid-template-columns: minmax(0, 1fr) minmax(170px, .7fr) auto;
        }

        .hc-search .hc-btn-secondary {
            grid-column: 1 / -1;
            justify-self: start;
        }
    }

    @media (max-width: 700px) {
        .hc-page {
            padding-left: 8px;
            padding-right: 8px;
        }

        .hc-header {
            align-items: stretch;
            flex-direction: column;
        }

        .hc-header-controls {
            align-items: stretch;
            flex-direction: column;
            width: 100%;
        }

        .hc-header-controls .hc-btn,
        .hc-header-controls .hc-record-badge {
            width: 100%;
            max-width: none;
            box-sizing: border-box;
        }

        .hc-edit-form {
            width: 100%;
            padding: 20px 16px 24px;
        }

        .hc-edit-grid {
            grid-template-columns: 1fr;
        }

        .hc-header .hc-btn-primary {
            width: 100%;
            max-width: none;
        }

        .hc-search {
            grid-template-columns: 1fr;
        }

        .hc-search input,
        .hc-search select,
        .hc-search .hc-btn {
            width: 100%;
        }

        .hc-count {
            justify-self: start;
        }

        .hc-stats {
            grid-template-columns: 1fr;
        }

        .hc-form-grid,
        .hc-detail-grid {
            grid-template-columns: 1fr;
        }

        .hc-field-full,
        .hc-detail-full {
            grid-column: auto;
        }

        .hc-form-actions {
            flex-direction: column-reverse;
        }

        .hc-form-actions .hc-btn {
            width: 100%;
        }
    }
</style>