<?php
declare(strict_types=1);
?>
<style>
    .inv-page {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        margin: 0 auto;
        padding: 10px 0 42px;
        box-sizing: border-box;
    }

    .inv-panel {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow: hidden;
        border: 1px solid #dbe5f0;
        border-radius: 18px;
        background: #ffffff;
        box-shadow: 0 15px 40px rgba(15, 35, 65, 0.08);
    }

    .inv-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 23px 25px;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #ffffff, #f7fbff);
    }

    .inv-header-copy {
        min-width: 0;
        flex: 1 1 auto;
    }

    .inv-header h1 {
        margin: 0 0 6px;
        color: #08264f;
        font-size: 25px;
    }

    .inv-header p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
        line-height: 1.5;
    }

    .inv-header-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 0 0 auto;
    }

    .inv-btn {
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
        white-space: nowrap;
    }

    .inv-btn-primary {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.22);
    }

    .inv-btn-primary:hover {
        background: #1d4ed8;
    }

    .inv-btn-secondary {
        background: #e9eef5;
        color: #334155;
    }

    .inv-btn-warning {
        background: #f59e0b;
        color: #ffffff;
    }

    .inv-btn-danger {
        background: #dc2626;
        color: #ffffff;
    }

    .inv-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        padding: 20px 24px 0;
    }

    .inv-stat {
        min-width: 0;
        padding: 17px;
        border: 1px solid #e2e8f0;
        border-radius: 13px;
        background: #f8fafc;
    }

    .inv-stat span {
        display: block;
        margin-bottom: 6px;
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .inv-stat strong {
        color: #0f2747;
        font-size: 24px;
    }

    .inv-toolbar {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: 14px;
        padding: 17px 24px;
        border-bottom: 1px solid #e8eef5;
        background: #fbfdff;
    }

    .inv-search {
        display: grid;
        grid-template-columns:
            minmax(220px, 1.4fr)
            minmax(150px, .7fr)
            minmax(140px, .6fr)
            auto
            auto;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .inv-search input,
    .inv-search select {
        width: 100%;
        min-width: 0;
        min-height: 42px;
        padding: 10px 13px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #ffffff;
        color: #0f172a;
        font: inherit;
        box-sizing: border-box;
        outline: none;
    }

    .inv-search input:focus,
    .inv-search select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .inv-count {
        white-space: nowrap;
        color: #475569;
        font-size: 14px;
        font-weight: 700;
    }

    .inv-alert {
        margin: 20px 24px 0;
        padding: 14px 16px;
        border-radius: 11px;
        font-size: 14px;
        font-weight: 700;
    }

    .inv-alert-success {
        border: 1px solid #bbf7d0;
        background: #f0fdf4;
        color: #166534;
    }

    .inv-alert-error {
        border: 1px solid #fecaca;
        background: #fff1f2;
        color: #b91c1c;
    }

    .inv-content {
        min-width: 0;
        padding: 22px 24px 26px;
    }

    .inv-table-wrapper {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: auto;
        border: 1px solid #e2e8f0;
        border-radius: 13px;
        scrollbar-width: thin;
    }

    .inv-table {
        width: 100%;
        min-width: 1120px;
        border-collapse: collapse;
    }

    .inv-table th,
    .inv-table td {
        padding: 12px 11px;
        border-bottom: 1px solid #e8eef5;
        text-align: left;
        vertical-align: middle;
        font-size: 12.5px;
        line-height: 1.45;
    }

    .inv-table th {
        background: #f8fafc;
        color: #334155;
        font-weight: 800;
    }

    .inv-table tbody tr:hover {
        background: #f8fbff;
    }

    .inv-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .inv-primary {
        display: block;
        color: #0f2747;
        font-size: 14px;
        font-weight: 800;
    }

    .inv-secondary {
        display: block;
        margin-top: 3px;
        color: #64748b;
        font-size: 12px;
    }

    .inv-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 28px;
        padding: 5px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        text-transform: capitalize;
        white-space: nowrap;
    }

    .inv-badge-ok {
        background: #dcfce7;
        color: #166534;
    }

    .inv-badge-low {
        background: #fef3c7;
        color: #92400e;
    }

    .inv-badge-out {
        background: #fee2e2;
        color: #991b1b;
    }

    .inv-badge-off {
        background: #e2e8f0;
        color: #475569;
    }

    .inv-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        min-width: 220px;
        white-space: nowrap;
    }

    .inv-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 32px;
        padding: 6px 8px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 800;
        text-decoration: none;
    }

    .inv-action-view {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .inv-action-edit {
        background: #fff7ed;
        color: #c2410c;
    }

    .inv-action-delete {
        background: #fff1f2;
        color: #be123c;
    }

    .inv-empty {
        padding: 48px 20px;
        border: 1px dashed #cbd5e1;
        border-radius: 14px;
        background: #fbfdff;
        text-align: center;
    }

    .inv-empty span {
        display: block;
        margin-bottom: 12px;
        font-size: 40px;
    }

    .inv-empty h2 {
        margin: 0 0 8px;
        color: #0f2747;
        font-size: 21px;
    }

    .inv-empty p {
        margin: 0 0 18px;
        color: #64748b;
    }

    .inv-form {
        width: min(1120px, 100%);
        margin: 0 auto;
        padding: 27px;
        box-sizing: border-box;
    }

    .inv-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
    }

    .inv-field {
        display: grid;
        gap: 8px;
        min-width: 0;
    }

    .inv-field-full {
        grid-column: 1 / -1;
    }

    .inv-field label {
        color: #334155;
        font-size: 14px;
        font-weight: 800;
    }

    .inv-field input,
    .inv-field select,
    .inv-field textarea {
        width: 100%;
        min-width: 0;
        padding: 12px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #ffffff;
        color: #0f172a;
        font: inherit;
        font-size: 14px;
        box-sizing: border-box;
        outline: none;
    }

    .inv-field input:focus,
    .inv-field select:focus,
    .inv-field textarea:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .inv-field textarea {
        min-height: 110px;
        resize: vertical;
    }

    .inv-help {
        color: #64748b;
        font-size: 12px;
        line-height: 1.45;
    }

    .inv-form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 28px;
        padding-top: 22px;
        border-top: 1px solid #e2e8f0;
    }

    .inv-detail-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .inv-detail {
        padding: 17px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #f8fafc;
    }

    .inv-detail-full {
        grid-column: 1 / -1;
    }

    .inv-detail span {
        display: block;
        margin-bottom: 6px;
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .inv-detail strong,
    .inv-detail p {
        margin: 0;
        color: #172033;
        font-size: 14px;
        line-height: 1.65;
        white-space: pre-wrap;
    }

    @media (max-width: 1100px) {
        .inv-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .inv-toolbar {
            grid-template-columns: 1fr;
        }

        .inv-search {
            grid-template-columns:
                minmax(220px, 1fr)
                minmax(140px, .6fr)
                minmax(140px, .6fr)
                auto;
        }

        .inv-search .inv-btn-secondary {
            grid-column: 1 / -1;
            justify-self: start;
        }

        .inv-count {
            justify-self: end;
        }
    }

    @media (max-width: 720px) {
        .inv-header {
            align-items: stretch;
            flex-direction: column;
        }

        .inv-header-actions {
            flex-direction: column;
            width: 100%;
        }

        .inv-header-actions .inv-btn {
            width: 100%;
        }

        .inv-search {
            grid-template-columns: 1fr;
        }

        .inv-search input,
        .inv-search select,
        .inv-search .inv-btn {
            width: 100%;
        }

        .inv-count {
            justify-self: start;
        }

        .inv-stats {
            grid-template-columns: 1fr;
        }

        .inv-form-grid,
        .inv-detail-grid {
            grid-template-columns: 1fr;
        }

        .inv-field-full,
        .inv-detail-full {
            grid-column: auto;
        }

        .inv-form-actions {
            flex-direction: column-reverse;
        }

        .inv-form-actions .inv-btn {
            width: 100%;
        }

        .inv-header,
        .inv-toolbar,
        .inv-content,
        .inv-form {
            padding-left: 18px;
            padding-right: 18px;
        }

        .inv-stats {
            padding-left: 18px;
            padding-right: 18px;
        }

        .inv-alert {
            margin-left: 18px;
            margin-right: 18px;
        }
    }
</style>