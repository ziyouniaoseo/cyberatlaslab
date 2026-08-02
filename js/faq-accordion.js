/**
 * =============================================================================
 * CR-System: FAQ Accordion Engine
 * Version: 2.0
 * =============================================================================
 *
 * Features:
 * - Accordion 排他模式
 * - 支持预设 .is-open
 * - 默认展开第一项
 * - ARIA 无障碍支持
 * - Resize 自动重算高度
 * - CSS 控制初始闭合状态
 * - 修复首次点击无法展开问题
 *
 * Required CSS:
 * .crs-faq__answer {
 *     height: 0;
 *     overflow: hidden;
 *     transition: height .3s ease;
 * }
 * =============================================================================
 */

document.addEventListener('DOMContentLoaded', () => {

    const items = document.querySelectorAll('.crs-faq__item');

    if (!items.length) {
        return;
    }

    /**
     * FAQ 注册表
     */
    const faqRegistry = Array.from(items)
        .map(item => ({
            element: item,
            button: item.querySelector('.crs-faq__question'),
            answer: item.querySelector('.crs-faq__answer')
        }))
        .filter(item => item.button && item.answer);

    if (!faqRegistry.length) {
        return;
    }

    /**
     * 展开项
     */
    const openItem = (entry) => {

        entry.element.classList.add('is-open');

        entry.button.setAttribute('aria-expanded', 'true');

        entry.answer.setAttribute('aria-hidden', 'false');

        entry.answer.style.height =
            `${entry.answer.scrollHeight}px`;
    };

    /**
     * 关闭项
     */
    const closeItem = (entry) => {

        entry.element.classList.remove('is-open');

        entry.button.setAttribute('aria-expanded', 'false');

        entry.answer.setAttribute('aria-hidden', 'true');

        entry.answer.style.height = '0px';
    };

    /**
     * 初始化
     */
    const initOpenState = () => {

        const presetOpenItems = faqRegistry.filter(
            entry => entry.element.classList.contains('is-open')
        );

        requestAnimationFrame(() => {

            if (presetOpenItems.length) {

                presetOpenItems.forEach(openItem);

            } else {

                openItem(faqRegistry[0]);

            }

        });
    };

    /**
     * 点击事件
     */
    faqRegistry.forEach(current => {

        current.button.setAttribute(
            'aria-expanded',
            current.element.classList.contains('is-open')
                ? 'true'
                : 'false'
        );

        current.answer.setAttribute(
            'aria-hidden',
            current.element.classList.contains('is-open')
                ? 'false'
                : 'true'
        );

        current.button.addEventListener('click', (e) => {

            e.preventDefault();

            const isOpen =
                current.element.classList.contains('is-open');

            // 关闭其它项
            faqRegistry.forEach(entry => {

                if (
                    entry !== current &&
                    entry.element.classList.contains('is-open')
                ) {
                    closeItem(entry);
                }

            });

            // 切换当前项
            if (isOpen) {

                closeItem(current);

            } else {

                openItem(current);

            }

        });

    });

    /**
     * 初始化执行
     */
    initOpenState();

    /**
     * Resize 防抖
     */
    let resizeTimer;

    window.addEventListener('resize', () => {

        clearTimeout(resizeTimer);

        resizeTimer = setTimeout(() => {

            faqRegistry.forEach(entry => {

                if (
                    entry.element.classList.contains('is-open')
                ) {

                    entry.answer.style.height =
                        `${entry.answer.scrollHeight}px`;

                }

            });

        }, 150);

    });

});