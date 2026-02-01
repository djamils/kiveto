// Scheduling Module - JavaScript enhancements

document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh waiting room every 30 seconds
    if (document.querySelector('.waiting-room-container')) {
        setInterval(() => {
            // Simple page reload for MVP
            // In production, use AJAX to update only the waiting room section
            const url = new URL(window.location);
            url.searchParams.set('_refresh', Date.now());
            
            // Silent refresh (could be improved with fetch + DOM update)
            console.log('Auto-refresh triggered');
        }, 30000); // 30 seconds
    }

    // Confirm before starting service
    const startServiceForms = document.querySelectorAll('form[action*="/start-service"]');
    startServiceForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm('Démarrer le service pour ce patient ?')) {
                e.preventDefault();
            }
        });
    });

    // Confirm before closing entry
    const closeEntryForms = document.querySelectorAll('form[action*="/close"]');
    closeEntryForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm('Terminer et fermer cette entrée ?')) {
                e.preventDefault();
            }
        });
    });

    // Set default datetime to now + 30 minutes for new appointments
    const datetimeInput = document.querySelector('input[name="startsAtUtc"]');
    if (datetimeInput && !datetimeInput.value) {
        const now = new Date();
        now.setMinutes(now.getMinutes() + 30);
        
        // Round to nearest 15 minutes
        const minutes = now.getMinutes();
        const roundedMinutes = Math.ceil(minutes / 15) * 15;
        now.setMinutes(roundedMinutes);
        now.setSeconds(0);
        
        // Format for datetime-local input
        const formatted = now.getFullYear() + '-' +
            String(now.getMonth() + 1).padStart(2, '0') + '-' +
            String(now.getDate()).padStart(2, '0') + 'T' +
            String(now.getHours()).padStart(2, '0') + ':' +
            String(now.getMinutes()).padStart(2, '0');
        
        datetimeInput.value = formatted;
    }

    // Highlight emergency entries
    const emergencyEntries = document.querySelectorAll('.list-group-item[data-emergency="true"]');
    emergencyEntries.forEach(entry => {
        entry.style.animation = 'pulse 2s infinite';
    });

    // Sound notification for new emergency (future enhancement)
    // Could use WebSocket or polling to detect new entries
});

// Helper: Format duration minutes to readable string
function formatDuration(minutes) {
    if (minutes < 60) {
        return `${minutes}min`;
    }
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return mins > 0 ? `${hours}h${mins}` : `${hours}h`;
}

// Helper: Calculate end time from start + duration
function calculateEndTime(startTime, durationMinutes) {
    const start = new Date(startTime);
    start.setMinutes(start.getMinutes() + durationMinutes);
    return start.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

// Export for potential use in other modules
window.SchedulingModule = {
    formatDuration,
    calculateEndTime
};
