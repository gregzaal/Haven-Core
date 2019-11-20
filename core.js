var click_functions = function(){

    // Grid option menus
    $('.grid-option').click(function() {
        var dropdown = $(this).children('.dropdown');
        if (dropdown.css('visibility') == 'hidden') {
            dropdown.css({'visibility': 'visible', 'opacity': '1'});
            $('.grid-option').not(this).children('.dropdown').css({'visibility': 'hidden', 'opacity': '0'});
        }else{
            dropdown.css({'visibility': 'hidden', 'opacity': '0'});
        }
    });

    // Problem text
    $('.problem-wrapper').children().mouseenter(function() {
        $(this).parent().children('.problem').addClass("problem-hover");
    });
    $('.problem-wrapper').children().mouseleave(function() {
        $(this).parent().children('.problem').removeClass("problem-hover");
    });

    // Navbar Mobile
    $('#navbar-toggle').click(function() {
        var navbar = $('#navbar');
        if (navbar.css("display") != "none"){
            navbar.css("display", "none");
        }else{
            navbar.css("display", "block");
        }
    });

    // Sidebar Mobile
    $('#sidebar-toggle').click(function() {
        var sidebar = $('#sidebar');
        if (sidebar.css("display") != "none"){
            sidebar.animate({'left': "-200px"}, 200, function(){
                sidebar.css("display", "none");
            });
        }else{
            sidebar.css("display", "block");
            sidebar.animate({'left': "0"}, 200);
        }
    });

    // Category balls scroller
    var scroll_cat_dist = $(".category-list-images a").width() + $("#sidebar").width();
    $('#scroll-cat-right').click(function() {
        $('.category-list-images').animate({'left': "-="+scroll_cat_dist}, 200, hide_cat_scroll_arrows);
    });
    $('#scroll-cat-left').click(function() {
        $('.category-list-images').animate({'left': "+="+scroll_cat_dist}, 200, hide_cat_scroll_arrows);

    });
    function hide_cat_scroll_arrows(){
        var start = $('#list-start-pos').offset().left;
        var end = $('#list-end-pos').offset().left;

        if (start > 0){
            $('.fade-gradient-left').addClass('hide');
            $('#scroll-cat-left').addClass('hide');
        }else{
            $('.fade-gradient-left').removeClass('hide');
            $('#scroll-cat-left').removeClass('hide');
        }

        if (end < $(window).width()-200){
            $('.fade-gradient-right').addClass('hide');
            $('#scroll-cat-right').addClass('hide');
        }else{
            $('.fade-gradient-right').removeClass('hide');
            $('#scroll-cat-right').removeClass('hide');
        }
    }

    // Lightbox
    $('.lightbox-trigger').click(function() {
        $('#lightbox-img').attr("src", "");
        $('#lightbox-wrapper').removeClass("hide");
        $('#lightbox-img').attr("src", $(this).attr("lightbox-src"));

        if ($("#artwork-name").length){  // Gallery
            $("#artwork-name").html($(this).attr("artwork-name"));
            $("#author-name").html($(this).attr("author-name"));
            $("#author-link").attr("href", $(this).attr("author-link"));
            $("#item-used-name").html($(this).attr("item-used-name"));
            $("#item-used-link").attr("href", $(this).attr("item-used-link"));

            if ($(this).attr("author-link") == "#"){
                $("#author-link").addClass("hide-link");
            }else{
                $("#author-link").removeClass("hide-link");
            }

            if ($(this).hasClass("gallery-click")){
                $.post("click.php", {id: $(this).attr("gallery-id")});
                console.log("click!");
            }
        }

        if ($("#href-dlbp-pretty").length){  // Backplates
            $("#href-dlbp-pretty").attr("href", $(this).attr("dlbp-pretty"));
            $("#href-dlbp-plain").attr("href", $(this).attr("dlbp-plain"));
            $("#href-dlbp-raw").attr("href", $(this).attr("dlbp-raw"));
            $("#href-dlbp-pretty").attr("download", $(this).attr("dlbp-pretty").substring($(this).attr("dlbp-pretty").lastIndexOf('/')+1));
            $("#href-dlbp-plain").attr("download", $(this).attr("dlbp-plain").substring($(this).attr("dlbp-plain").lastIndexOf('/')+1));
            $("#href-dlbp-raw").attr("download", $(this).attr("dlbp-raw").substring($(this).attr("dlbp-raw").lastIndexOf('/')+1));
        }
    });
    $('#lightbox-close, #lightbox-wrapper').click(function() {
        $('#lightbox-wrapper').addClass("hide");
        $('#lightbox-img').attr("src", $(this).attr("lightbox-src"));
    });
    $('#href-dlbp-pretty, #href-dlbp-plain, #href-dlbp-raw').click(function(evt) {
        evt.stopPropagation();  // Prevent lightbox closing after downloading backplate
    });
};

var on_load = function(){

    // Push footer to bottom
    var h = $("#header").height();
    var f = $("#footer").height();
    var css = "calc(100vh - "+h+"px - "+f+"px)";
    $('#push-footer').css("min-height", css);

    // Lazy image loading
    const images = document.querySelectorAll('[data-src]');
    const config = {
        rootMargin: '0px 0px 50px 0px',
        threshold: 0
    };
    let loaded = 0;
    let observer = new IntersectionObserver(function (entries, self) {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            // console.log(`${entry.target.alt} is in the viewport!`);
            preloadImage(entry.target);
            // Stop watching and load the image
            self.unobserve(entry.target);
        }
    });
    }, config);
    images.forEach(image => {
        observer.observe(image);
    });
    function preloadImage(img) {
        const src = img.getAttribute('data-src');
        if (!src) { return; }
        $(img).on('load', function(){
            $(img).siblings('.thumbnail-proxy').addClass('hide');
        });
        img.src = src;
    }
};

$(document).ready(click_functions);
$(document).ready(on_load);
