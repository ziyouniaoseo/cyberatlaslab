/**
 * Offer Countdown - 营销倒计时模块 (优化版)
 * 
 * 改进点：
 * 1. 支持多实例
 * 2. 增加错误防御
 * 3. 优化过期后的 DOM 处理
 */
(function () {
    'use strict';
    const updateCountdown = () => {
        // 这里必须匹配 PHP 里的 cal-js-countdown
        const elements = document.querySelectorAll('.cal-js-countdown');
        const now = new Date();

        elements.forEach(el => {
            const endDateStr = el.dataset.endDate;
            if (!endDateStr) return;
            const endDate = new Date(endDateStr);

            if (isNaN(endDate.getTime())) {
                console.warn('Invalid countdown date:', endDateStr);
                return;
            }

            const diffTime = endDate - now;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            el.classList.remove('cal-glass-alert__days--urgent', 'cal-glass-alert__days--expired');
            let newText = '';

            if (diffTime <= 0) {
                newText = 'Expired';
                el.classList.add('cal-glass-alert__days--expired');
            } else if (diffDays === 1) {
                newText = 'Last Day';
                el.classList.add('cal-glass-alert__days--urgent');
            } else {
                newText = `${diffDays} Days`;
            }

            if (el.textContent !== newText) {
                el.textContent = newText;
            }
        });
    };

    const init = () => {
        updateCountdown();
        setInterval(updateCountdown, 3600000);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();