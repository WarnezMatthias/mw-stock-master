<?php // Append the css-style.

function stockCss() {
	if (strpos($_SERVER['REQUEST_URI'], '/change-stock/') != false) {
		wp_enqueue_style('mw-holiday', stockPlugin()->plugin_url() . '/css/mw_stock.css');
	}
}
add_action('wp_enqueue_scripts', 'stockCss', 1);

// 1. Register new endpoint slug to use for My Account page
function ts_custom_change_stock() {
	add_rewrite_endpoint('change-stock', EP_ROOT | EP_PAGES);

	if (get_option('mw_stock_flush_rewrite_rules_flag')) {
		flush_rewrite_rules();
		delete_option('mw_stock_flush_rewrite_rules_flag');
	}
}
add_action('init', 'ts_custom_change_stock');

// 2. Add new query var
function ts_custom_change_stock_query_vars($vars) {
	$vars[] = 'change-stock';
	return $vars;
}
add_filter('woocommerce_get_query_vars', 'ts_custom_change_stock_query_vars', 0);

// 3. Insert the new endpoint into the My Account menu
function ts_custom_change_stock_link_my_account($items) {
	$user = wp_get_current_user();
	$roles = $user->roles;

	if (in_array('mw_stock', $roles) === false) {
		return $items;
	}

	$items['change-stock'] = 'Voorraadbeheer';


	return $items;
}
add_filter('woocommerce_account_menu_items', 'ts_custom_change_stock_link_my_account');

// 4. Add content to the new endpoint
function ts_custom_stock_content() {
	$user = wp_get_current_user();
	$roles = $user->roles;

	if (in_array('mw_stock', $roles) === false) {
		return;
	}

	$wpdb = $GLOBALS['wpdb'];

	$products = $wpdb->get_results('select 
									id as id, 
									post_title as product,
									(select meta_Value from wp_postmeta where meta_key = "_stock_status" and post_id = p.id) as stock
									from wp_posts p
									where post_type = "product" and post_Status = "publish" order by post_title');

?>
	<h2>Voorraadbeheer</h2>
	<p style="font-size:12px;">Selecteer alle producten die niet in voorraad zijn. Deze producten kunnen door de klanten niet besteld worden.</p>

	<input type="text" id="searchProduct" onkeyup="searchProduct()" placeholder="Zoek product" title="Type in a name">
	<p>
		<label class="switch">
			<input type="checkbox" id="productsNotInStock" onclick="displayProductsNotInStock()">
			<span class="slider round"></span>
		</label>
		Toon enkel producten die niet in stock zijn
	</p>

	<form id="holiday" method="post">
		<button type="submit">Opslaan</button>
		<br> <br>
		<table id="stockProductTable">
			<tr>
				<th>Product</th>
				<th>Niet in voorraad</th>
			</tr>
			<?php
			foreach ($products as $product) {

				$checked = $product->stock == "outofstock" ? "checked" : "";

				echo '<tr>';
				echo '<td>' . $product->product . '</td>';
				echo '<td> 
						<label class="switch">
  							<input  type="checkbox" name="outOfStockProducts[]" value="' . $product->id . '" ' . $checked . ' />
							<span class="slider round"></span>
						</label>
						</td>';
				echo '</tr>';
			}
			?>
		</table>
		<input type="hidden" name="action" id="action" value="post_change-stock_form" />
	</form>

	<script>
		function searchProduct() {
			var input, filter, table, tr, td, i, txtValue;
			input = document.getElementById("searchProduct");
			filter = input.value.toUpperCase();
			table = document.getElementById("stockProductTable");
			tr = table.getElementsByTagName("tr");
			for (i = 0; i < tr.length; i++) {
				td = tr[i].getElementsByTagName("td")[0];
				if (td) {
					txtValue = td.textContent || td.innerText;
					if (txtValue.toUpperCase().indexOf(filter) > -1) {
						tr[i].style.display = "";
					} else {
						tr[i].style.display = "none";
					}
				}
			}
		}

		function displayProductsNotInStock() {
			var input, filter, table, tr, td, i, txtValue;
			input = document.getElementById("productsNotInStock");
			table = document.getElementById("stockProductTable");
			tr = table.getElementsByTagName("tr");
			for (i = 1; i < tr.length; i++) {
				checkbox = tr[i].getElementsByTagName("td")[1].getElementsByTagName("input")[0];
				if (input.checked) {
					if (!checkbox.checked) {
						tr[i].style.display = "none";
					}
				} else {
					tr[i].style.display = "";
				}
			}
		}
	</script>
<?php


}

// @important-note	"add_action" must follow 'woocommerce_account_{your-endpoint-slug}_endpoint' format
add_action('woocommerce_account_change-stock_endpoint', 'ts_custom_stock_content');

add_action('init', 'saveStock');
function saveStock() {

	// Check if there is any post data and if it comes from our form
	if (empty($_POST) || !isset($_POST['action']) || $_POST['action'] != 'post_change-stock_form') {
		return;
	}

	$user = wp_get_current_user();
	$roles = $user->roles;

	if (in_array('mw_stock', $roles) === false) {
		return;
	}

	$wpdb = $GLOBALS['wpdb'];

	$products = implode(",", $_POST['outOfStockProducts']);

	$queryUpdateInStock = 'update wp_posts p inner join wp_postmeta pm  on p.id = pm.post_id and pm.meta_key = "_stock_status"
							set meta_value = "instock"
							where p.post_type = "product" and p.post_Status = "publish"';

	$queryUpdateOutOfStock = 'update wp_posts p inner join wp_postmeta pm  on p.id = pm.post_id and pm.meta_key = "_stock_status"
								set meta_value = "outofstock"
								where p.post_type = "product" and p.post_Status = "publish" and p.id in (' . $products . ')';

	$wpdb->query($queryUpdateInStock);
	$wpdb->query($queryUpdateOutOfStock);

	header("Refresh: 0");
}
