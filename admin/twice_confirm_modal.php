<?php
/**
 * OnBolt Digital Experience Platform - Admin Action Confirmation & Success Notification
 * 
 * Rules:
 * 1. NO confirmation popup for UPDATE or SAVING actions (submits natively).
 * 2. On successful update or save, display a prominent popup modal:
 *    - "UPDATED SUCCESSFULLY"
 *    - "SAVED SUCCESSFULLY"
 * 3. Deletion actions retain confirmation to prevent accidental loss.
 */
declare(strict_types=1);

$phpSuccessMsg = '';
if (!empty($msg)) {
    $phpSuccessMsg = (string)$msg;
} elseif (!empty($GLOBALS['msg'])) {
    $phpSuccessMsg = (string)$GLOBALS['msg'];
} elseif (!empty($_SESSION['flash_msg'])) {
    $phpSuccessMsg = (string)$_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}
?>

<!-- 1. Success Notification Popup Modal (For Saved / Updated Successfully) -->
<div id="otcSuccessPopupOverlay" class="otc-success-overlay" style="display:none;" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="otc-success-backdrop" id="otcSuccessBackdrop"></div>
    <div class="otc-success-card">
        <button type="button" class="otc-success-close" id="otcSuccessClose" title="Close">&times;</button>
        <div class="otc-success-icon-wrap">
            <div class="otc-success-checkmark-circle">
                <span class="otc-success-checkmark-icon">&#10003;</span>
            </div>
        </div>
        <h3 id="otcSuccessTitle" class="otc-success-title">SAVED SUCCESSFULLY</h3>
        <p id="otcSuccessDesc" class="otc-success-desc" style="display:none;"></p>
        <div class="otc-success-actions">
            <button type="button" id="otcSuccessBtnOk" class="otc-success-btn">OK</button>
        </div>
    </div>
</div>

<!-- 2. Deletion Confirmation Popup Modal -->
<div id="otcModalOverlay" class="otc-overlay" style="display:none;" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="otc-backdrop" id="otcBackdrop"></div>
    <div class="otc-modal-card">
        
        <div class="otc-header-bar">
            <div id="otcStepBadge" class="otc-step-badge otc-badge-step1-delete">
                <span class="otc-step-dot"></span>
                <span id="otcStepBadgeText">CONFIRM DELETION</span>
            </div>
            <button type="button" class="otc-close-icon" id="otcBtnClose" title="Cancel &amp; Close">&times;</button>
        </div>

        <div class="otc-body">
            <div id="otcIconWrap" class="otc-icon-circle otc-icon-delete">
                <span id="otcIcon">⚠️</span>
            </div>

            <div class="otc-text-content">
                <h3 id="otcTitle" class="otc-title">Confirm Deletion</h3>
                <p id="otcDesc" class="otc-desc">Are you sure you want to proceed with deleting this record?</p>

                <div id="otcItemBox" class="otc-item-callout" style="display:none;">
                    <div class="otc-item-label" id="otcItemLabel">Target Item:</div>
                    <div class="otc-item-val" id="otcItemVal">Item Name</div>
                </div>

                <div id="otcStep2Notice" class="otc-step2-notice otc-notice-delete" style="display:none;">
                    <div class="otc-notice-icon">⚠️</div>
                    <div class="otc-notice-text" id="otcNoticeText">
                        <strong>Caution:</strong> This deletion action cannot be reversed once confirmed.
                    </div>
                </div>
            </div>
        </div>

        <div class="otc-footer-actions">
            <button type="button" id="otcBtnCancel" class="otc-btn otc-btn-secondary">
                Cancel
            </button>
            <button type="button" id="otcBtnProceed" class="otc-btn otc-btn-primary-delete">
                <span id="otcBtnProceedText">Yes, Delete Record</span>
            </button>
        </div>

    </div>
</div>

<style>
/* ==================== SUCCESS POPUP MODAL STYLES ==================== */
.otc-success-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 99999999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    box-sizing: border-box;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.22s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
.otc-success-overlay.otc-active {
    opacity: 1;
    visibility: visible;
}
.otc-success-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(11, 17, 30, 0.65);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
}
.otc-success-card {
    position: relative;
    z-index: 2;
    background: #ffffff;
    width: 100%;
    max-width: 420px;
    border-radius: 24px;
    padding: 2.25rem 2rem 2rem;
    box-shadow: 0 25px 60px -10px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(0, 0, 0, 0.05);
    text-align: center;
    transform: scale(0.9) translateY(12px);
    transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.otc-success-overlay.otc-active .otc-success-card {
    transform: scale(1) translateY(0);
}
.otc-success-close {
    position: absolute;
    top: 1rem;
    right: 1.25rem;
    background: none;
    border: none;
    font-size: 1.6rem;
    color: #94a3b8;
    cursor: pointer;
    line-height: 1;
    padding: 0.25rem;
    border-radius: 6px;
    transition: color 0.15s ease;
}
.otc-success-close:hover {
    color: #0f172a;
}
.otc-success-icon-wrap {
    display: flex;
    justify-content: center;
    margin-bottom: 1.25rem;
}
.otc-success-checkmark-circle {
    width: 76px;
    height: 76px;
    border-radius: 50%;
    background: #22c55e;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: 900;
    box-shadow: 0 10px 25px rgba(34, 197, 94, 0.45);
    animation: otcSuccessPop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes otcSuccessPop {
    0% { transform: scale(0.4); opacity: 0; }
    70% { transform: scale(1.15); }
    100% { transform: scale(1); opacity: 1; }
}
.otc-success-title {
    font-size: 1.35rem;
    font-weight: 900;
    letter-spacing: 0.04em;
    color: #0f172a;
    margin: 0 0 0.5rem 0;
    text-transform: uppercase;
    font-family: inherit;
}
.otc-success-desc {
    font-size: 0.95rem;
    color: #64748b;
    margin: 0 0 1.5rem 0;
    line-height: 1.45;
}
.otc-success-actions {
    margin-top: 1.5rem;
    display: flex;
    justify-content: center;
}
.otc-success-btn {
    background: #ff5500;
    color: #ffffff;
    font-weight: 800;
    font-size: 1rem;
    border: none;
    border-radius: 9999px;
    padding: 0.75rem 2.75rem;
    cursor: pointer;
    box-shadow: 0 6px 18px rgba(255, 85, 0, 0.35);
    transition: all 0.2s ease;
}
.otc-success-btn:hover {
    background: #e04a00;
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(255, 85, 0, 0.45);
}

/* ==================== DELETION CONFIRMATION MODAL STYLES ==================== */
.otc-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.25rem;
    box-sizing: border-box;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.22s ease, visibility 0.22s ease;
}
.otc-overlay.otc-active {
    opacity: 1;
    visibility: visible;
}
.otc-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(11, 17, 30, 0.75);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
}
.otc-modal-card {
    position: relative;
    z-index: 2;
    background: #ffffff;
    width: 100%;
    max-width: 480px;
    border-radius: 18px;
    box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(0, 0, 0, 0.06);
    overflow: hidden;
    transform: scale(0.95) translateY(8px);
    transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
.otc-overlay.otc-active .otc-modal-card {
    transform: scale(1) translateY(0);
}
.otc-header-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.4rem;
    border-bottom: 1px solid #f1f5f9;
}
.otc-step-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.3rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}
.otc-badge-step1-delete {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fee2e2;
}
.otc-step-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: currentColor;
}
.otc-close-icon {
    background: transparent;
    border: none;
    font-size: 1.5rem;
    line-height: 1;
    color: #94a3b8;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 6px;
    transition: color 0.15s ease;
}
.otc-close-icon:hover {
    color: #0f172a;
}
.otc-body {
    padding: 1.5rem 1.5rem 1.25rem 1.5rem;
    display: flex;
    gap: 1.25rem;
}
.otc-icon-circle {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    flex-shrink: 0;
}
.otc-icon-delete {
    background: #fee2e2;
    color: #dc2626;
}
.otc-text-content {
    flex: 1;
    min-width: 0;
}
.otc-title {
    margin: 0 0 0.4rem 0;
    font-size: 1.2rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.3;
}
.otc-desc {
    margin: 0 0 0.85rem 0;
    font-size: 0.92rem;
    color: #475569;
    line-height: 1.5;
}
.otc-item-callout {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.65rem 0.9rem;
    margin-bottom: 0.85rem;
}
.otc-item-label {
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.04em;
    margin-bottom: 0.2rem;
}
.otc-item-val {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
    word-break: break-word;
}
.otc-step2-notice {
    display: flex;
    gap: 0.65rem;
    padding: 0.75rem 0.9rem;
    border-radius: 10px;
    font-size: 0.85rem;
    line-height: 1.45;
    margin-top: 0.5rem;
}
.otc-notice-delete {
    background: #fff1f2;
    border: 1px solid #fecdd3;
    color: #9f1239;
}
.otc-footer-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.5rem 1.4rem 1.5rem;
    background: #fafafa;
    border-top: 1px solid #f1f5f9;
}
.otc-btn {
    border: none;
    border-radius: 10px;
    font-size: 0.92rem;
    font-weight: 700;
    padding: 0.65rem 1.25rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.18s ease;
}
.otc-btn-secondary {
    background: #ffffff;
    color: #475569;
    border: 1px solid #cbd5e1;
}
.otc-btn-secondary:hover {
    background: #f1f5f9;
    color: #0f172a;
}
.otc-btn-primary-delete {
    background: #dc2626;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.35);
}
.otc-btn-primary-delete:hover {
    background: #b91c1c;
    transform: translateY(-1px);
}
</style>

<script>
(function() {
    'use strict';

    // State management for deletion
    var pendingDeleteForm = null;
    var clickedSubmitButton = null;

    // DOM Elements - Deletion
    var overlay = document.getElementById('otcModalOverlay');
    var backdrop = document.getElementById('otcBackdrop');
    var btnClose = document.getElementById('otcBtnClose');
    var btnCancel = document.getElementById('otcBtnCancel');
    var btnProceed = document.getElementById('otcBtnProceed');
    var btnProceedText = document.getElementById('otcBtnProceedText');
    var itemBox = document.getElementById('otcItemBox');
    var itemLabel = document.getElementById('otcItemLabel');
    var itemVal = document.getElementById('otcItemVal');

    // DOM Elements - Success Popup
    var successOverlay = document.getElementById('otcSuccessPopupOverlay');
    var successTitle = document.getElementById('otcSuccessTitle');
    var successBtnOk = document.getElementById('otcSuccessBtnOk');
    var successClose = document.getElementById('otcSuccessClose');
    var successBackdrop = document.getElementById('otcSuccessBackdrop');
    var successTimer = null;

    function openDeleteModal(itemName) {
        if (!overlay) return;
        if (itemName) {
            itemBox.style.display = 'block';
            itemLabel.textContent = 'Item to be Deleted:';
            itemVal.textContent = itemName;
        } else {
            itemBox.style.display = 'none';
        }
        overlay.style.display = 'flex';
        void overlay.offsetWidth;
        overlay.classList.add('otc-active');
        overlay.setAttribute('aria-hidden', 'false');
        if (btnProceed) btnProceed.focus();
    }

    function closeDeleteModal() {
        if (!overlay) return;
        overlay.classList.remove('otc-active');
        overlay.setAttribute('aria-hidden', 'true');
        setTimeout(function() {
            overlay.style.display = 'none';
            pendingDeleteForm = null;
            clickedSubmitButton = null;
        }, 220);
    }

    // Determine whether an action is delete or save/update
    function detectAction(form, button) {
        if (button && button.getAttribute('data-action')) {
            var a = button.getAttribute('data-action').toLowerCase();
            if (a.indexOf('delete') !== -1 || a === 'unlink' || a === 'remove') return 'delete';
            if (a.indexOf('update') !== -1 || a === 'edit') return 'update';
            return 'save';
        }
        if (form.getAttribute('data-confirm')) {
            var c = form.getAttribute('data-confirm').toLowerCase();
            if (c.indexOf('delete') !== -1 || c === 'unlink' || c === 'remove') return 'delete';
        }

        // Check hidden input action
        var actionInput = form.querySelector('input[name="action"]');
        if (actionInput) {
            var val = (actionInput.value || '').toLowerCase();
            if (val === 'delete' || val === 'unlink' || val === 'unlink_school_product' || val === 'remove' || val.indexOf('delete') !== -1) {
                return 'delete';
            }
            if (val === 'update' || val.indexOf('update') !== -1 || val === 'edit') {
                return 'update';
            }
            if (val === 'add' || val === 'create' || val === 'save' || val.indexOf('save') !== -1) {
                return 'save';
            }
        }

        // Check button text or styling
        if (button) {
            var btnText = (button.textContent || '').trim().toLowerCase();
            if (/delete|remove|unlink|discard/i.test(btnText)) {
                return 'delete';
            }
            if (/update|edit/i.test(btnText)) {
                return 'update';
            }
            if (/save|upload|create|add|confirm|send/i.test(btnText)) {
                return 'save';
            }
            var btnStyle = (button.getAttribute('style') || '').toLowerCase();
            if (btnStyle.indexOf('dc2626') !== -1 || button.classList.contains('btn-danger')) {
                return 'delete';
            }
        }

        // URL check
        if (window.location.search.indexOf('edit=') !== -1) {
            return 'update';
        }

        return 'save';
    }

    function detectItemName(form, button) {
        if (form.getAttribute('data-item-name')) {
            return form.getAttribute('data-item-name');
        }
        if (button && button.getAttribute('data-item-name')) {
            return button.getAttribute('data-item-name');
        }
        var nameInput = form.querySelector('input[name="name"], input[name="product_name"], input[name="school_name"], input[name="multi_name"], input[name="sub_single_name"], input[name="full_name"]');
        if (nameInput && nameInput.value && nameInput.value.trim().length > 0) {
            return nameInput.value.trim();
        }
        return '';
    }

    // Intercept form submission
    function handleFormSubmit(e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM') return;

        // Ignore GET forms
        var method = (form.getAttribute('method') || 'GET').toUpperCase();
        if (method !== 'POST') return;

        // Skip if marked as no-confirm
        if (form.getAttribute('data-no-confirm') === 'true') return;

        // If already confirmed delete, allow submission
        if (form._deleteConfirmed) {
            return;
        }

        // HTML5 Validation check
        if (form.checkValidity && !form.checkValidity()) {
            form.reportValidity();
            return;
        }

        var action = detectAction(form, clickedSubmitButton);

        // =========================================================================
        // REQUIREMENT: NO NEED CONFIRMATION FOR UPDATE AND SAVING!
        // Allow direct submission immediately without any modal prompt.
        // =========================================================================
        if (action !== 'delete') {
            var actionTitle = (action === 'update') ? 'UPDATED SUCCESSFULLY' : 'SAVED SUCCESSFULLY';
            try {
                sessionStorage.setItem('otc_pending_action', actionTitle);
            } catch(err) {}

            // Ensure clicked submit button name/value are forwarded if needed
            if (clickedSubmitButton && clickedSubmitButton.name && clickedSubmitButton.value) {
                var existingInput = form.querySelector('input[name="' + clickedSubmitButton.name + '"]');
                if (!existingInput) {
                    var hiddenBtnInput = document.createElement('input');
                    hiddenBtnInput.type = 'hidden';
                    hiddenBtnInput.name = clickedSubmitButton.name;
                    hiddenBtnInput.value = clickedSubmitButton.value;
                    form.appendChild(hiddenBtnInput);
                } else {
                    existingInput.value = clickedSubmitButton.value;
                }
            }

            // Return and let native submission proceed!
            return;
        }

        // ONLY Deletion actions get a confirmation prompt:
        e.preventDefault();
        e.stopPropagation();

        pendingDeleteForm = form;
        var itemName = detectItemName(form, clickedSubmitButton);
        openDeleteModal(itemName);
    }

    // Track clicked submit button
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('button[type="submit"], input[type="submit"], button:not([type]), a.otc-confirm-action');
        if (btn) {
            clickedSubmitButton = btn;
        }
    }, true);

    document.addEventListener('submit', handleFormSubmit, true);

    // Deletion Modal buttons
    if (btnProceed) {
        btnProceed.addEventListener('click', function() {
            if (!pendingDeleteForm) {
                closeDeleteModal();
                return;
            }
            btnProceed.disabled = true;
            btnProceedText.textContent = 'Deleting...';
            pendingDeleteForm._deleteConfirmed = true;

            setTimeout(function() {
                overlay.classList.remove('otc-active');
                pendingDeleteForm.submit();
            }, 100);
        });
    }

    if (btnCancel) btnCancel.addEventListener('click', closeDeleteModal);
    if (btnClose) btnClose.addEventListener('click', closeDeleteModal);
    if (backdrop) backdrop.addEventListener('click', closeDeleteModal);

    // =========================================================================
    // SUCCESS POPUP MODAL HANDLER (UPDATED SUCCESSFULLY / SAVED SUCCESSFULLY)
    // =========================================================================
    function triggerSuccessPopup(titleText) {
        if (!successOverlay || !successTitle) return;

        var finalTitle = 'SAVED SUCCESSFULLY';
        var lower = (titleText || '').toLowerCase();
        if (lower.indexOf('update') !== -1 || lower.indexOf('edit') !== -1) {
            finalTitle = 'UPDATED SUCCESSFULLY';
        } else if (lower.indexOf('save') !== -1 || lower.indexOf('create') !== -1 || lower.indexOf('add') !== -1) {
            finalTitle = 'SAVED SUCCESSFULLY';
        } else if (titleText && titleText.trim().length > 0) {
            finalTitle = titleText.trim().toUpperCase();
        }

        successTitle.textContent = finalTitle;
        successOverlay.style.display = 'flex';
        void successOverlay.offsetWidth;
        successOverlay.classList.add('otc-active');
        successOverlay.setAttribute('aria-hidden', 'false');

        if (successBtnOk) successBtnOk.focus();

        if (successTimer) clearTimeout(successTimer);
        // Auto-dismiss after 3.2 seconds
        successTimer = setTimeout(closeSuccessPopup, 3200);
    }

    function closeSuccessPopup() {
        if (successTimer) clearTimeout(successTimer);
        if (!successOverlay) return;
        successOverlay.classList.remove('otc-active');
        successOverlay.setAttribute('aria-hidden', 'true');
        setTimeout(function() {
            successOverlay.style.display = 'none';
        }, 220);
    }

    if (successBtnOk) successBtnOk.addEventListener('click', closeSuccessPopup);
    if (successClose) successClose.addEventListener('click', closeSuccessPopup);
    if (successBackdrop) successBackdrop.addEventListener('click', closeSuccessPopup);

    // Check for success condition on page load
    function checkAndShowSuccess() {
        var phpMsg = <?= json_encode($phpSuccessMsg) ?>;
        var storedAction = null;
        try {
            storedAction = sessionStorage.getItem('otc_pending_action');
            if (storedAction) {
                sessionStorage.removeItem('otc_pending_action');
            }
        } catch(e) {}

        // Check if there is an error on the page
        var errorEl = document.querySelector('.alert-danger, div[style*="rgba(239, 68, 68"], div[style*="color: #b91c1c"]');
        if (errorEl && errorEl.textContent && errorEl.textContent.trim().length > 0) {
            return; // Don't show success if error occurred
        }

        if (phpMsg && phpMsg.trim().length > 0) {
            triggerSuccessPopup(phpMsg);
        } else if (storedAction) {
            triggerSuccessPopup(storedAction);
        } else {
            // Check DOM for green alert
            var alertEl = document.querySelector('.alert-success, div[style*="rgba(34, 197, 94"]');
            if (alertEl && alertEl.textContent && alertEl.textContent.trim().length > 0) {
                var cleanTxt = alertEl.textContent.trim().replace(/^[✓✔\s]+/, '');
                triggerSuccessPopup(cleanTxt);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkAndShowSuccess);
    } else {
        checkAndShowSuccess();
    }

    // Keyboard support: Escape closes any open modal, Enter on success closes it
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (successOverlay && successOverlay.classList.contains('otc-active')) {
                e.preventDefault();
                closeSuccessPopup();
            } else if (overlay && overlay.classList.contains('otc-active')) {
                e.preventDefault();
                closeDeleteModal();
            }
        } else if (e.key === 'Enter') {
            if (successOverlay && successOverlay.classList.contains('otc-active')) {
                e.preventDefault();
                closeSuccessPopup();
            }
        }
    });

})();
</script>
