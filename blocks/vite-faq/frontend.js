// Client-side logic
document.addEventListener('DOMContentLoaded', function() {
    initializeFAQ();
});

function initializeFAQ() {
    const faqContainers = document.querySelectorAll('.faq-container');
    
    faqContainers.forEach(container => {
        if (container.getAttribute('data-faq-initialized') === 'true') {
            return;
        }
        
        const faqItems = container.querySelectorAll('.faq-item');
        
        faqItems.forEach(item => {
            const checkbox = item.querySelector('input[type="checkbox"]');
            const question = item.querySelector('.faq-question');
            const answer = item.querySelector('.faq-answer');
            
            if (checkbox && question && answer) {
                // Remove existing event listeners to prevent duplicates
                const newQuestion = question.cloneNode(true);
                question.parentNode.replaceChild(newQuestion, question);
                
                newQuestion.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleFAQ(checkbox, answer, newQuestion);
                });
                
                checkbox.addEventListener('change', function() {
                    updateFAQState(this, answer, newQuestion);
                });
                
                updateFAQState(checkbox, answer, newQuestion);
            }
        });
        
        container.setAttribute('data-faq-initialized', 'true');
    });
}

function toggleFAQ(checkbox, answer, question) {
    checkbox.checked = !checkbox.checked;
    
    checkbox.dispatchEvent(new Event('change'));
}

function updateFAQState(checkbox, answer, question) {
    if (checkbox.checked) {
        answer.style.maxHeight = answer.scrollHeight + "px";
        question.classList.add('expanded');
        answer.setAttribute('aria-expanded', 'true');
        question.setAttribute('aria-expanded', 'true');
    } else {
        answer.style.maxHeight = "0px";
        question.classList.remove('expanded');
        answer.setAttribute('aria-expanded', 'false');
        question.setAttribute('aria-expanded', 'false');
    }
}

function reinitializeFAQ() {
    const containers = document.querySelectorAll('.faq-container[data-faq-initialized="true"]');
    containers.forEach(container => {
        container.removeAttribute('data-faq-initialized');
    });
    
    initializeFAQ();
}

if (typeof window !== 'undefined') {
    window.faqBlockUtils = {
        initialize: initializeFAQ,
        reinitialize: reinitializeFAQ,
        toggle: toggleFAQ
    };
}

document.addEventListener('DOMContentLoaded', function() {
    const observer = new MutationObserver(function(mutations) {
        let shouldReinitialize = false;
        
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length > 0) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1 && 
                        (node.classList.contains('faq-container') || 
                         node.querySelector('.faq-container'))) {
                        shouldReinitialize = true;
                    }
                });
            }
        });
        
        // Debounce the reinitialization to avoid multiple calls
        if (shouldReinitialize) {
            clearTimeout(window.faqReinitTimeout);
            window.faqReinitTimeout = setTimeout(initializeFAQ, 100);
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});

// Handle WordPress block editor compatibility
if (typeof wp !== 'undefined' && wp.domReady) {
    wp.domReady(function() {
        initializeFAQ();
    });
}