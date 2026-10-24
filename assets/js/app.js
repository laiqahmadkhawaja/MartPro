/**
 * MartPro Core UI Application Script
 */

$(document).ready(function() {
    // Theme toggle & persistence
    const currentTheme = localStorage.getItem('martpro_theme') || 'light';
    document.documentElement.setAttribute('data-bs-theme', currentTheme);

    $('#theme-toggle-btn').on('click', function() {
        const theme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('martpro_theme', theme);
        $(this).find('i').toggleClass('bi-moon bi-sun');
    });

    // Mobile sidebar toggle
    $('#sidebar-toggle-btn').on('click', function() {
        $('#sidebar').toggleClass('show');
        if ($('#sidebar').hasClass('show')) {
            $('body').append('<div class="sidebar-backdrop" id="sidebar-backdrop"></div>');
            $('#sidebar-backdrop').on('click', function() {
                $('#sidebar').removeClass('show');
                $(this).remove();
            });
        } else {
            $('#sidebar-backdrop').remove();
        }
    });

    // Store switcher change
    $('#store-switcher').on('change', function() {
        const storeId = $(this).val();
        MartPro.ajax(MartPro.baseUrl + '/modules/settings/ajax/switch_store.php', {
            data: { store_id: storeId }
        }).then(function() {
            window.location.reload();
        });
    });
});
