const appBase = (document.body.dataset.appBase || '').replace(/\/$/, '');
const appUrl = (path) => `${appBase}/${String(path).replace(/^\/+/, '')}`;

for (const element of document.querySelectorAll('[href], [src], [action], [formaction]')) {
    for (const attribute of ['href', 'src', 'action', 'formaction']) {
        const value = element.getAttribute(attribute);
        if (value && value.startsWith('/') && !value.startsWith('//') &&
            !(appBase && (value === appBase || value.startsWith(`${appBase}/`)))) {
            element.setAttribute(attribute, `${appBase}${value}`);
        }
    }
}

const adminConfirmModal = document.querySelector('#adminConfirmModal');
const adminConfirmTitle = adminConfirmModal?.querySelector('[data-admin-confirm-title]');
const adminConfirmMessage = adminConfirmModal?.querySelector('[data-admin-confirm-message]');
const adminConfirmDetails = adminConfirmModal?.querySelector('[data-admin-confirm-details]');
const adminConfirmError = adminConfirmModal?.querySelector('[data-admin-confirm-error]');
const adminConfirmSubmit = adminConfirmModal?.querySelector('[data-admin-confirm-submit]');
let pendingAdminForm = null;

const adminConfirmFields = [
    ['Mã đơn', 'confirmCode'], ['Tin nhắn', 'confirmMessageId'], ['Khách hàng', 'confirmCustomer'], ['Email', 'confirmEmail'], ['Sân', 'confirmCourt'],
    ['Số tiền', 'confirmAmount'], ['Phương thức', 'confirmMethod'], ['Thanh toán', 'confirmStatus'],
];

const showAdminConfirm = (form, message) => {
    if (!adminConfirmModal || !window.bootstrap?.Modal) return false;
    pendingAdminForm = form;
    adminConfirmTitle.textContent = form.dataset.confirmTitle || 'Xác nhận thao tác';
    adminConfirmMessage.textContent = message || 'Bạn có chắc chắn muốn thực hiện thao tác này?';
    adminConfirmError.hidden = true;
    adminConfirmError.textContent = '';
    adminConfirmDetails.replaceChildren();
    for (const [label, key] of adminConfirmFields) {
        if (!form.dataset[key]) continue;
        const term = document.createElement('dt');
        const value = document.createElement('dd');
        term.textContent = label;
        value.textContent = form.dataset[key];
        adminConfirmDetails.append(term, value);
    }
    adminConfirmDetails.hidden = adminConfirmDetails.childElementCount === 0;
    adminConfirmSubmit.textContent = form.dataset.confirmButton || 'Xác nhận';
    adminConfirmSubmit.classList.remove('btn-danger', 'btn-success', 'btn-warning');
    adminConfirmSubmit.classList.add(form.dataset.confirmTone === 'danger' ? 'btn-danger' : (form.dataset.confirmTone === 'warning' ? 'btn-warning' : 'btn-success'));
    adminConfirmSubmit.disabled = false;
    bootstrap.Modal.getOrCreateInstance(adminConfirmModal).show();
    return true;
};

document.querySelectorAll('form[data-confirm], form[data-admin-booking-form]').forEach((form) => form.addEventListener('submit', (event) => {
    if (form.dataset.confirmApproved === 'true') {
        delete form.dataset.confirmApproved;
        return;
    }
    const cancelBooking = form.matches('[data-admin-booking-form]') && form.querySelector('[name="status"]')?.value === 'cancelled';
    if (!form.hasAttribute('data-confirm') && !cancelBooking) return;
    const message = cancelBooking
        ? `${form.dataset.confirmMessage || 'Bạn có chắc muốn hủy đơn?'}${form.dataset.confirmCode ? ` (${form.dataset.confirmCode})` : ''}`
        : (form.dataset.confirmMessage || form.dataset.confirm || 'Bạn có chắc chắn muốn thực hiện thao tác này?');
    if (showAdminConfirm(form, message)) {
        event.preventDefault();
    } else if (adminConfirmModal) {
        event.preventDefault();
    } else if (!window.confirm(message)) {
        event.preventDefault();
    }
}));

adminConfirmSubmit?.addEventListener('click', async () => {
    if (!pendingAdminForm) return;
    const form = pendingAdminForm;
    adminConfirmSubmit.disabled = true;
    const originalText = form.dataset.confirmButton || 'Xác nhận';
    adminConfirmSubmit.textContent = 'Đang xử lý…';
    adminConfirmError.hidden = true;

    if (form.hasAttribute('data-confirm-ajax')) {
        try {
            const response = await fetch(form.dataset.confirmEndpoint || form.action || window.location.href, {
                method: 'POST', body: new FormData(form),
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                credentials: 'same-origin',
            });
            const responseText = await response.text();
            let result;
            try {
                result = JSON.parse(responseText);
            } catch {
                const responsePage = responseText.match(/<title[^>]*>(.*?)<\/title>/is)?.[1]?.replace(/\s+/g, ' ').trim();
                const pageHint = responsePage ? ` (${responsePage})` : '';
                throw new Error(`Máy chủ trả về trang HTML thay vì kết quả thao tác${pageHint}. Hãy tải lại trang Admin rồi thử lại.`);
            }
            if (!response.ok || !result.ok) throw new Error(result.error || result.message || 'Không thể hoàn tất thao tác.');
            bootstrap.Modal.getOrCreateInstance(adminConfirmModal).hide();
            if (form.hasAttribute('data-confirm-stay')) {
                form.dispatchEvent(new CustomEvent('admin-confirm-success', {bubbles: true, detail: result}));
            } else {
                window.location.reload();
            }
        } catch (error) {
            adminConfirmError.textContent = error.message || 'Không thể kết nối máy chủ. Vui lòng thử lại.';
            adminConfirmError.hidden = false;
            adminConfirmSubmit.disabled = false;
            adminConfirmSubmit.textContent = originalText;
        }
        return;
    }

    form.dataset.confirmApproved = 'true';
    form.requestSubmit();
    bootstrap.Modal.getOrCreateInstance(adminConfirmModal).hide();
});

document.querySelectorAll('.flash-wrap .alert').forEach((alert) => {
    window.setTimeout(() => {
        const instance = window.bootstrap?.Alert?.getOrCreateInstance(alert);
        instance?.close();
    }, 4500);
});

document.querySelectorAll('form[data-disable-on-submit]').forEach((form) => form.addEventListener('submit', () => {
    const submitButton = form.querySelector('button[type="submit"], button:not([type])');
    if (!submitButton) return;
    submitButton.disabled = true;
    submitButton.textContent = 'Đang xử lý…';
}));

const navToggle = document.querySelector('.nav-toggle');
const navMenu = document.querySelector('.nav-menu');
if (navToggle && navMenu) {
    navToggle.addEventListener('click', () => {
        const expanded = navToggle.getAttribute('aria-expanded') === 'true';
        navToggle.setAttribute('aria-expanded', String(!expanded));
        navMenu.classList.toggle('is-open', !expanded);
    });
}

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) return;
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        button.textContent = reveal ? 'Ẩn' : 'Hiện';
        button.setAttribute('aria-label', reveal ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
        button.setAttribute('aria-pressed', String(reveal));
    });
});

document.querySelectorAll('img').forEach((image) => {
    image.addEventListener('error', () => {
        if (!image.dataset.fallback) {
            image.dataset.fallback = 'true';
            image.src = appUrl(image.classList.contains('hero-slide') ? 'assets/images/hero-city-court.png' : 'assets/images/other.png');
        }
    });
});

document.querySelectorAll('[data-booking-form]').forEach((form) => {
    const slots = [...form.querySelectorAll('input[name="slots[]"]')];
    const total = form.querySelector('[data-booking-total]');
    const hoursLabel = form.querySelector('[data-booking-hours]');
    const submit = form.querySelector('[data-booking-submit]');
    const pricePerHour = Number(form.dataset.pricePerHour || 0);
    const formatMoney = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 });

    const updateSummary = () => {
        const selected = slots.filter((slot) => slot.checked);
        const hours = selected.reduce((sum, slot) => sum + Number(slot.dataset.slotHours || 0), 0);
        if (total) total.textContent = `${formatMoney.format(pricePerHour * hours)} đ`;
        if (hoursLabel) hoursLabel.textContent = `${hours} giờ`;
        if (submit) submit.disabled = selected.length === 0 || pricePerHour <= 0;
    };

    slots.forEach((slot) => slot.addEventListener('change', updateSummary));
    updateSummary();
});

const heroSlider = document.querySelector('[data-hero-slider]');
if (heroSlider) {
    const slides = [...heroSlider.querySelectorAll('.hero-slide')];
    const indicators = [...document.querySelectorAll('[data-hero-slide]')];
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (slides.length === 4 && indicators.length === slides.length) {
        let activeSlide = 0;
        let timerId = null;
        let requestToken = 0;
        const warmed = new Set([0]);

        const warmSlide = (index) => {
            const source = slides[index].getAttribute('src') || slides[index].dataset.src;
            if (!source || warmed.has(index)) return;
            warmed.add(index);
            const preload = new Image();
            preload.fetchPriority = 'low';
            preload.src = source;
        };

        const showSlide = (index) => {
            const targetIndex = (index + slides.length) % slides.length;
            const target = slides[targetIndex];
            const token = ++requestToken;
            const activate = () => {
                if (token !== requestToken) return;
                slides.forEach((slide, slideIndex) => slide.classList.toggle('is-active', slideIndex === targetIndex));
                indicators.forEach((indicator, slideIndex) => {
                    const selected = slideIndex === targetIndex;
                    indicator.classList.toggle('is-active', selected);
                    indicator.setAttribute('aria-pressed', String(selected));
                });
                activeSlide = targetIndex;
                if (!reducedMotion) warmSlide((activeSlide + 1) % slides.length);
            };

            if (!target.getAttribute('src') && target.dataset.src) target.src = target.dataset.src;
            if (target.complete && target.naturalWidth > 0) activate();
            else target.addEventListener('load', activate, { once: true });
        };

        const stopTimer = () => {
            if (timerId !== null) window.clearInterval(timerId);
            timerId = null;
        };
        const startTimer = () => {
            if (reducedMotion || timerId !== null || document.hidden) return;
            timerId = window.setInterval(() => showSlide(activeSlide + 1), 5500);
        };
        indicators.forEach((indicator) => indicator.addEventListener('click', () => {
            showSlide(Number(indicator.dataset.heroSlide) || 0);
            stopTimer();
            startTimer();
        }));
        document.addEventListener('visibilitychange', () => document.hidden ? stopTimer() : startTimer());
        if (!reducedMotion) warmSlide(1);
        startTimer();
    }
}

const ratingHint = document.querySelector('[data-rating-hint]');
if (ratingHint) {
    const descriptions = {
        1: '1/5 - Rất không hài lòng',
        2: '2/5 - Không hài lòng',
        3: '3/5 - Bình thường',
        4: '4/5 - Tốt',
        5: '5/5 - Rất tốt',
    };
    document.querySelectorAll('.star-options input[name="rating"]').forEach((input) => {
        input.addEventListener('change', () => {
            ratingHint.textContent = descriptions[input.value] || 'Chọn mức đánh giá';
        });
    });
}

const chatRoot = document.querySelector('[data-chat], [data-chat-admin]');
if (chatRoot) {
    const api = chatRoot.dataset.api;
    const csrf = chatRoot.dataset.csrf;
    const isAdminChat = chatRoot.hasAttribute('data-chat-admin');
    const isWidget = chatRoot.dataset.widget === 'true';
    const thread = chatRoot.querySelector('[data-chat-thread]');
    const form = chatRoot.querySelector('[data-chat-form]');
    const errorBox = chatRoot.querySelector('[data-chat-error]');
    let conversationId = Number(chatRoot.dataset.selected || 0);
    let lastMessageId = 0;
    let pollTimer = null;
    let busy = false;
    let chatOpen = !isWidget || !chatRoot.hidden;
    const findMessageRow = (messageId) => [...(thread?.children || [])].find((item) => item.dataset.messageId === String(messageId));
    const request = async (url, options = {}) => {
        const response = await fetch(url, { credentials: 'same-origin', ...options });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Có lỗi xảy ra.');
        return data;
    };
    const addMessage = (item) => {
        if (!thread) return;
        const row = findMessageRow(item.id) || document.createElement('article');
        row.className = `chat-message${Number(item.sender_id) === Number(document.body.dataset.userId || -1) ? ' mine' : ''}`;
        row.dataset.messageId = String(item.id);
        row.dataset.senderName = item.full_name || '';
        row.replaceChildren();
        const content = document.createElement('div');
        if (Number(item.is_deleted)) {
            content.className = 'chat-message-removed';
            content.textContent = 'Tin nhắn đã bị quản trị viên xóa.';
        } else {
            content.textContent = item.message || (item.image_path ? '' : '[Tin nhắn]');
        }
        row.append(content);
        if (item.image_path && !Number(item.is_deleted)) {
            const image = document.createElement('img');
            image.src = appUrl(item.image_path);
            image.alt = 'Ảnh trong cuộc trò chuyện';
            image.dataset.lightbox = '1';
            row.append(image);
        }
        const time = document.createElement('small');
        time.textContent = new Date(String(item.created_at).replace(' ', 'T')).toLocaleString('vi-VN');
        row.append(time);
        if (isAdminChat && !Number(item.is_deleted)) {
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'chat-delete-message';
            removeButton.textContent = 'Xóa';
            removeButton.title = 'Xóa tin nhắn cho cả hai bên';
            removeButton.setAttribute('aria-label', 'Xóa tin nhắn cho cả hai bên');
            removeButton.dataset.deleteMessage = String(item.id);
            row.append(removeButton);
        }
        if (!row.isConnected) thread.append(row);
        thread.scrollTop = thread.scrollHeight;
        lastMessageId = Math.max(lastMessageId, Number(item.id));
    };
    const poll = async () => {
        if (busy || (isWidget && !chatOpen) || (isAdminChat && !conversationId)) return;
        busy = true;
        try {
            const params = new URLSearchParams({ action: 'poll', after: '0' });
            if (conversationId) params.set('conversation_id', String(conversationId));
            const result = await request(`${api}?${params}`);
            conversationId = Number(result.conversation.id);
            chatRoot.dataset.selected = String(conversationId);
            const closeId = chatRoot.querySelector('[data-chat-close] input[name="conversation_id"]');
            if (closeId) closeId.value = String(conversationId);
            const heading = chatRoot.querySelector('[data-chat-heading]');
            if (heading) heading.textContent = `${result.conversation.name} · ${result.conversation.email} · ${result.conversation.phone || 'Không có SĐT'} · ${result.conversation.booking_count} booking · ${result.conversation.status === 'open' ? 'Đang hỗ trợ' : 'Đã kết thúc'}`;
            const currentIds = new Set(result.messages.map((item) => String(item.id)));
            result.messages.forEach(addMessage);
            thread?.querySelectorAll('[data-message-id]').forEach((row) => {
                if (!currentIds.has(row.dataset.messageId)) row.remove();
            });
        } catch (error) {
            if (errorBox) errorBox.textContent = error.message;
        } finally { busy = false; }
    };
    const refreshList = async () => {
        if (!isAdminChat) return;
        const list = chatRoot.querySelector('[data-chat-list]');
        if (!list) return;
        const search = new URLSearchParams(window.location.search);
        search.set('action', 'list');
        const result = await request(`${api}?${search}`);
        list.replaceChildren();
        result.conversations.forEach((conversation) => {
            const link = document.createElement('a');
            link.href = '#';
            link.className = `chat-conversation-item${Number(conversation.id) === conversationId ? ' active' : ''}`;
            link.addEventListener('click', (event) => {
                event.preventDefault(); conversationId = Number(conversation.id); lastMessageId = 0;
                chatRoot.dataset.selected = String(conversationId); thread.replaceChildren(); poll(); refreshList();
            });
            const title = document.createElement('strong'); title.textContent = conversation.full_name;
            const unread = document.createElement('b'); if (Number(conversation.unread)) unread.textContent = String(conversation.unread);
            const preview = document.createElement('small'); preview.textContent = conversation.last_text || (conversation.last_image ? 'Đã gửi ảnh' : (conversation.last_message_at ? 'Tin nhắn đã bị xóa' : 'Chưa có tin nhắn'));
            const timestamp = document.createElement('small'); timestamp.textContent = conversation.last_message_at ? new Date(String(conversation.last_message_at).replace(' ', 'T')).toLocaleString('vi-VN') : 'Chưa có tin nhắn';
            link.append(title, unread, preview, timestamp); list.append(link);
        });
    };
    const imageInput = chatRoot.querySelector('[data-chat-image]');
    const preview = chatRoot.querySelector('[data-chat-preview]');
    if (imageInput && preview) imageInput.addEventListener('change', () => {
        preview.replaceChildren(); const file = imageInput.files[0];
        if (file) { const img = document.createElement('img'); img.className = 'chat-preview-thumb'; img.src = URL.createObjectURL(file); img.alt = 'Ảnh xem trước'; const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = '×'; remove.setAttribute('aria-label','Bỏ ảnh'); remove.onclick = () => { imageInput.value=''; preview.replaceChildren(); }; preview.append(img,remove); }
    });
    const messageInput = form?.querySelector('textarea[name="message"]');
    const deleteForm = chatRoot.querySelector('[data-chat-delete-form]');
    thread?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-delete-message]');
        if (!button || !isAdminChat || !deleteForm) return;
        const row = button.closest('[data-message-id]');
        const idInput = deleteForm.querySelector('[data-delete-message-id]');
        const conversationInput = deleteForm.querySelector('[data-delete-conversation]');
        if (!row || !idInput || !conversationInput) return;
        deleteForm.dataset.confirmEndpoint = new URL(`${api}?action=delete_message`, window.location.href).toString();
        idInput.value = row.dataset.messageId;
        conversationInput.value = String(conversationId);
        deleteForm.dataset.confirmMessageId = `#${row.dataset.messageId}`;
        deleteForm.dataset.confirmCustomer = row.dataset.senderName || 'Thành viên cuộc trò chuyện';
        showAdminConfirm(deleteForm, deleteForm.dataset.confirmMessage);
    });
    document.addEventListener('admin-confirm-success', (event) => {
        if (event.target !== deleteForm) return;
        poll();
        refreshList().catch(() => {});
        if (errorBox) errorBox.textContent = '';
    });
    messageInput?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.shiftKey || event.isComposing || event.keyCode === 229) return;
        event.preventDefault();
        form.requestSubmit();
    });
    if (form) form.addEventListener('submit', async (event) => {
        event.preventDefault(); if (busy) return; busy = true;
        try {
            const payload = new FormData(form); payload.append('action','send'); payload.append('csrf',csrf);
            if (conversationId) payload.append('conversation_id',String(conversationId));
            const result = await request(api,{method:'POST',body:payload});
            busy = false;
            form.reset(); if(preview)preview.replaceChildren(); if(errorBox)errorBox.textContent='';
            await poll(); if(isAdminChat)await refreshList();
        } catch(error) { if(errorBox)errorBox.textContent=error.message; }
        finally { busy=false; }
    });
    const setChatOpen = (open) => {
        chatOpen = open;
        if (isWidget) chatRoot.hidden = !open;
        const toggle = document.querySelector('[data-chat-toggle]');
        if (toggle) toggle.setAttribute('aria-expanded', String(open));
        if (open) poll();
    };
    document.querySelector('[data-chat-toggle]')?.addEventListener('click', () => setChatOpen(!chatOpen));
    chatRoot.querySelector('button[data-chat-close]')?.addEventListener('click', () => setChatOpen(false));
    document.querySelectorAll('[data-chat-prefill]').forEach((button) => button.addEventListener('click', () => {
        const textInput = form?.querySelector('textarea[name="message"]');
        if (textInput) textInput.value = button.dataset.chatPrefill || '';
        setChatOpen(true);
        textInput?.focus();
    }));
    if (isAdminChat) refreshList().then(poll).catch((error)=>{if(errorBox)errorBox.textContent=error.message;});
    else {
        const textInput=form?.querySelector('textarea[name="message"]');
        if(textInput && chatRoot.dataset.prefill) textInput.value=chatRoot.dataset.prefill;
        if(!isWidget || chatOpen) poll();
    }
    pollTimer = window.setInterval(() => { if(!isWidget || chatOpen)poll(); if(isAdminChat)refreshList().catch(()=>{}); }, 4000);
    thread?.addEventListener('click',(event)=>{const image=event.target.closest('[data-lightbox]');if(!image)return;const overlay=document.createElement('div');overlay.className='chat-lightbox';const full=document.createElement('img');full.src=image.src;full.alt=image.alt;overlay.append(full);overlay.addEventListener('click',()=>overlay.remove());document.body.append(overlay);});
}

const unreadBadge = document.querySelector('[data-unread-badge], [data-admin-unread]');
if (unreadBadge) {
    const api = appUrl('chat/api.php');
    const updateUnread = async () => { try { const response=await fetch(`${api}?action=unread`,{credentials:'same-origin'});const data=await response.json();const count=Number(data.unread||0);const badge=unreadBadge.querySelector('b');badge.textContent=String(count);unreadBadge.classList.toggle('has-unread',count>0); } catch {} };
    updateUnread(); window.setInterval(updateUnread,5000);
}

document.querySelectorAll('[data-copy-payment]').forEach((button) => button.addEventListener('click', async () => {
    const paymentBox = button.closest('[data-payment-status]');
    const feedback = paymentBox?.querySelector('[data-copy-feedback]');
    const text = button.dataset.copyPayment === 'account-number'
        ? paymentBox?.querySelector('[data-account-number]')?.textContent?.trim()
        : paymentBox?.querySelector('[data-transfer-text]')?.textContent?.trim();
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        if (feedback) {
            feedback.textContent = 'Đã sao chép.';
            feedback.hidden = false;
            window.clearTimeout(feedback.hideTimer);
            feedback.hideTimer = window.setTimeout(() => { feedback.hidden = true; }, 1800);
        }
    } catch {
        if (feedback) {
            feedback.textContent = 'Không thể sao chép tự động.';
            feedback.hidden = false;
            window.clearTimeout(feedback.hideTimer);
            feedback.hideTimer = window.setTimeout(() => { feedback.hidden = true; }, 1800);
        }
    }
}));

document.querySelectorAll('[data-vietqr-image]').forEach((image) => image.addEventListener('error', () => {
    image.hidden = true;
    const notice = document.createElement('div');
    notice.className = 'alert alert-warning';
    notice.textContent = 'QR không tải được. Kiểm tra kết nối Internet rồi thử tải lại trang thanh toán.';
    image.insertAdjacentElement('afterend', notice);
}, { once: true }));

const paymentModal = document.getElementById('bankPaymentModal');
const showPaymentModal = () => {
    if (paymentModal && window.bootstrap?.Modal) window.bootstrap.Modal.getOrCreateInstance(paymentModal).show();
};
document.querySelectorAll('[data-open-payment]').forEach((button) => button.addEventListener('click', showPaymentModal));
if (paymentModal?.hasAttribute('data-auto-open-payment')) showPaymentModal();

document.querySelectorAll('[data-payment-status][data-status="pending"]').forEach((box) => {
    const endpoint = box.dataset.endpoint;
    const bookingId = box.dataset.bookingId;
    let paymentTimer;
    const pollPayment = async () => {
        try {
            const response = await fetch(`${endpoint}?booking_id=${encodeURIComponent(bookingId)}`, { credentials: 'same-origin' });
            if (!response.ok) return;
            const result = await response.json();
            if (result.status === 'pending') return;
            if (paymentTimer) window.clearInterval(paymentTimer);
            box.dataset.status = result.status;
            if (result.status === 'paid') {
                box.classList.add('is-paid');
                box.innerHTML = '<div class="payment-paid-mark">✓</div><h2 class="h5">Thanh toán thành công</h2><p>Quản trị viên đã xác nhận thanh toán của bạn.</p>';
            } else {
                box.innerHTML = '<h2 class="h5">Trạng thái thanh toán đã thay đổi</h2><p>Vui lòng xem trạng thái mới nhất trong lịch sử đặt sân hoặc liên hệ hỗ trợ.</p>';
            }
        } catch { /* Giữ trạng thái hiện tại nếu kết nối tạm thời lỗi. */ }
    };
    pollPayment();
    paymentTimer = window.setInterval(pollPayment, 4000);
});
