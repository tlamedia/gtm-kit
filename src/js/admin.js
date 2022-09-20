jQuery(function ($) {

	/**
	 * Set the initial active tab in the settings pages.
	 *
	 * @returns {void}
	 */
	function setInitialActiveTab() {
		let activeTabId = window.location.hash.replace("#top#", "");
		/* In some cases, the second # gets replace by %23, which makes the tab
		 * switching not work unless we do this. */
		if (activeTabId.search("#top") !== -1) {
			activeTabId = window.location.hash.replace("#top%23", "");
		}
		/*
		 * WordPress uses fragment identifiers for its own in-page links, e.g.
		 * `#wpbody-content` and other plugins may do that as well. Also, facebook
		 * adds a `#_=_` see PR 506. In these cases and when it's empty, default
		 * to the first tab.
		 */
		if ("" === activeTabId || "#" === activeTabId.charAt(0)) {
			/*
			 * Reminder: jQuery attr() gets the attribute value for only the first
			 * element in the matched set so this will always be the first tab id.
			 */
			activeTabId = jQuery(".gtmkit-tab").attr("id");
		}

		jQuery("#" + activeTabId).addClass("active");
		jQuery("#" + activeTabId + "-tab").addClass("nav-tab-active").trigger("click");
	}


	$(document).ready(function () {

		// Handle the settings pages tabs.
		$("#gtmkit-tabs").find("a").on("click", function () {
			$("#gtmkit-tabs").find("a").removeClass("nav-tab-active");
			$(".gtmkit-tab").removeClass("active");

			let id = $(this).attr("id").replace("-tab", "");
			let activeTab = $("#" + id);
			activeTab.addClass("active");
			$(this).addClass("nav-tab-active");
			if (activeTab.hasClass("nosave")) {
				$("#gtmkit-submit-container").hide();
			} else {
				$("#gtmkit-submit-container").show();
			}

			//$( window ).trigger( "yoast-seo-tab-change" );
		});


		$(".gtmkit-help-button").on("click", function () {
			let $button = $(this),
				helpPanel = $("#" + $button.attr("aria-controls")),
				isPanelVisible = helpPanel.is(":visible");

			$(helpPanel).slideToggle(200, function () {
				$button.attr("aria-expanded", !isPanelVisible);
			});
		});

		setInitialActiveTab();
	});

});
