/**
 * Flexible Preiskalkulations-Engine - Frontend
 */

const API_BASE = '/src/api/pricing';
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

class PricingEngine {
    constructor() {
        this.currentAssetType = null;
        this.tiers = [];
        this.volumeDiscounts = [];
        this.surcharges = [];
        this.bundles = [];
        this.currentCustomerClient = null;
        this.currentCustomerItems = [];

        this.init();
    }

    init() {
        this.attachEventListeners();
        this.loadAllData();
    }

    attachEventListeners() {
        // Price Calculator
        document.getElementById('priceCalculatorForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.calculatePrice();
        });

        // Tiers
        document.getElementById('tier_asset_type')?.addEventListener('change', (e) => {
            this.currentAssetType = e.target.value;
            this.loadTiers();
        });

        document.getElementById('btn_add_tier')?.addEventListener('click', () => {
            this.openTierModal();
        });

        document.getElementById('tierForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveTier();
        });

        // Volume Discounts
        document.getElementById('btn_add_volume')?.addEventListener('click', () => {
            this.openVolumeModal();
        });

        document.getElementById('volumeForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveVolumeDiscount();
        });

        // Surcharges
        document.getElementById('btn_add_surcharge')?.addEventListener('click', () => {
            this.openSurchargeModal();
        });

        document.getElementById('surchargeForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveSurcharge();
        });

        // Bundles
        document.getElementById('btn_add_bundle')?.addEventListener('click', () => {
            this.openBundleModal();
        });

        document.getElementById('bundleForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveBundle();
        });

        document.getElementById('btn_bundle_add_item')?.addEventListener('click', () => {
            this.addBundleItemRow();
        });

        // Customer Pricing
        document.getElementById('customer_client')?.addEventListener('change', (e) => {
            this.loadCustomerPricing(e.target.value);
        });

        document.getElementById('btn_add_customer_item')?.addEventListener('click', () => {
            this.addCustomerItemRow();
        });

        document.getElementById('btn_save_customer')?.addEventListener('click', () => {
            this.saveCustomerPricing();
        });
    }

    async loadAllData() {
        await this.loadTiers();
        await this.loadVolumeDiscounts();
        await this.loadSurcharges();
        await this.loadBundles();
    }

    // ===== PRICE CALCULATOR =====
    async calculatePrice() {
        const assetTypeId = parseInt(document.getElementById('calc_asset_type')?.value) || 0;
        const days = parseInt(document.getElementById('calc_days')?.value) || 1;
        const quantity = parseInt(document.getElementById('calc_quantity')?.value) || 1;
        const clientId = parseInt(document.getElementById('calc_client')?.value) || 0;
        const startDate = document.getElementById('calc_date')?.value;

        if (!assetTypeId) {
            alert('Bitte Artikel auswählen');
            return;
        }

        try {
            const response = await fetch(`${API_BASE}/calculate.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': CSRF_TOKEN,
                },
                body: new URLSearchParams({
                    asset_type_id: assetTypeId,
                    days: days,
                    quantity: quantity,
                    client_id: clientId || '',
                    start_date: startDate || '',
                }),
            });

            const data = await response.json();
            if (!data.success) throw new Error(data.message);

            this.displayPriceResult(data.data);
        } catch (error) {
            alert('Fehler bei Preisberechnung: ' + error.message);
        }
    }

    displayPriceResult(data) {
        const result = document.getElementById('calculatorResult');
        document.getElementById('result_base').textContent = this.formatCurrency(data.base_price);
        document.getElementById('result_final').textContent = this.formatCurrency(data.final_price);

        // Show/hide discount rows
        ['tier', 'volume', 'customer'].forEach(type => {
            const row = document.getElementById(`result_${type}_row`);
            const value = data[`${type}_discount`];
            if (value > 0) {
                row.style.display = '';
                document.getElementById(`result_${type}`).textContent = this.formatCurrency(value);
            } else {
                row.style.display = 'none';
            }
        });

        // Seasonal surcharge
        const seasonalRow = document.getElementById('result_seasonal_row');
        if (data.seasonal_surcharge > 0) {
            seasonalRow.style.display = '';
            document.getElementById('result_seasonal').textContent = this.formatCurrency(data.seasonal_surcharge);
        } else {
            seasonalRow.style.display = 'none';
        }

        // Breakdown
        const breakdown = document.getElementById('result_breakdown');
        breakdown.innerHTML = data.breakdown.map(item => `<div>• ${item}</div>`).join('');

        result.style.display = '';
    }

    // ===== TIER PRICING =====
    async loadTiers() {
        if (!this.currentAssetType) {
            document.getElementById('tiersTable')?.querySelector('tbody').innerHTML = '';
            return;
        }

        try {
            const response = await fetch(`${API_BASE}/tiers.php?asset_type_id=${this.currentAssetType}`);
            const data = await response.json();
            this.tiers = data.data.tiers || [];
            this.renderTiers();
        } catch (error) {
            console.error('Error loading tiers:', error);
        }
    }

    renderTiers() {
        const tbody = document.getElementById('tiersTable')?.querySelector('tbody');
        if (!tbody) return;

        tbody.innerHTML = this.tiers.map(tier => `
            <tr>
                <td>${this.getAssetTypeName(tier.asset_type_id)}</td>
                <td>${tier.min_days}</td>
                <td>${tier.max_days ? tier.max_days : '∞'}</td>
                <td>${this.formatCurrency(tier.price_per_day)}</td>
                <td>
                    <button class="btn btn-xs btn-warning" onclick="pricingEngine.editTier(${tier.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-xs btn-danger" onclick="pricingEngine.deleteTier(${tier.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    openTierModal(tierId = null) {
        const modal = document.getElementById('tierModal');
        if (tierId) {
            const tier = this.tiers.find(t => t.id === tierId);
            if (tier) {
                document.getElementById('tier_id').value = tier.id;
                document.getElementById('tier_asset').value = tier.asset_type_id;
                document.getElementById('tier_min').value = tier.min_days;
                document.getElementById('tier_max').value = tier.max_days || '';
                document.getElementById('tier_price').value = tier.price_per_day;
            }
        } else {
            document.getElementById('tier_id').value = '';
            document.getElementById('tier_asset').value = this.currentAssetType || '';
            document.getElementById('tier_min').value = 1;
            document.getElementById('tier_max').value = '';
            document.getElementById('tier_price').value = '';
        }
        $(modal).modal('show');
    }

    editTier(tierId) {
        this.openTierModal(tierId);
    }

    async saveTier() {
        const form = document.getElementById('tierForm');
        const formData = new FormData(form);
        formData.append('csrf_token', CSRF_TOKEN);

        try {
            const response = await fetch(`${API_BASE}/tiers.php`, {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();
            if (!data.success) throw new Error(data.message);

            $(document.getElementById('tierModal')).modal('hide');
            this.loadTiers();
        } catch (error) {
            alert('Fehler beim Speichern: ' + error.message);
        }
    }

    async deleteTier(tierId) {
        if (!confirm('Staffelpreis wirklich löschen?')) return;

        try {
            const response = await fetch(`${API_BASE}/tiers_delete.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': CSRF_TOKEN,
                },
                body: new URLSearchParams({ id: tierId }),
            });

            const data = await response.json();
            if (!data.success) throw new Error(data.message);

            this.loadTiers();
        } catch (error) {
            alert('Fehler beim Löschen: ' + error.message);
        }
    }

    // ===== VOLUME DISCOUNTS =====
    async loadVolumeDiscounts() {
        try {
            const response = await fetch(`${API_BASE}/volume_discounts.php`);
            const data = await response.json();
            this.volumeDiscounts = data.data.discounts || [];
            this.renderVolumeDiscounts();
        } catch (error) {
            console.error('Error loading volume discounts:', error);
        }
    }

    renderVolumeDiscounts() {
        const tbody = document.getElementById('volumeTable')?.querySelector('tbody');
        if (!tbody) return;

        tbody.innerHTML = this.volumeDiscounts.map(discount => `
            <tr>
                <td>${discount.min_quantity}</td>
                <td>${discount.max_quantity ? discount.max_quantity : '∞'}</td>
                <td>${discount.discount_percent}%</td>
                <td>
                    <button class="btn btn-xs btn-warning" onclick="pricingEngine.editVolumeDiscount(${discount.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-xs btn-danger" onclick="pricingEngine.deleteVolumeDiscount(${discount.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    openVolumeModal(discountId = null) {
        const modal = document.getElementById('volumeModal');
        if (discountId) {
            const discount = this.volumeDiscounts.find(d => d.id === discountId);
            if (discount) {
                document.getElementById('volume_id').value = discount.id;
                document.getElementById('volume_min').value = discount.min_quantity;
                document.getElementById('volume_max').value = discount.max_quantity || '';
                document.getElementById('volume_discount').value = discount.discount_percent;
            }
        } else {
            document.getElementById('volume_id').value = '';
            document.getElementById('volume_min').value = 1;
            document.getElementById('volume_max').value = '';
            document.getElementById('volume_discount').value = '';
        }
        $(modal).modal('show');
    }

    editVolumeDiscount(discountId) {
        this.openVolumeModal(discountId);
    }

    async saveVolumeDiscount() {
        const form = document.getElementById('volumeForm');
        const formData = new FormData(form);

        try {
            const response = await fetch(`${API_BASE}/volume_discounts.php`, {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();
            if (!data.success) throw new Error(data.message);

            $(document.getElementById('volumeModal')).modal('hide');
            this.loadVolumeDiscounts();
        } catch (error) {
            alert('Fehler beim Speichern: ' + error.message);
        }
    }

    async deleteVolumeDiscount(discountId) {
        if (!confirm('Mengenrabatt wirklich löschen?')) return;

        try {
            const response = await fetch(`${API_BASE}/volume_discounts_delete.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': CSRF_TOKEN,
                },
                body: new URLSearchParams({ id: discountId }),
            });

            const data = await response.json();
            if (!data.success) throw new Error(data.message);

            this.loadVolumeDiscounts();
        } catch (error) {
            alert('Fehler beim Löschen: ' + error.message);
        }
    }

    // ===== SEASONAL SURCHARGES =====
    async loadSurcharges() {
        try {
            const response = await fetch(`${API_BASE}/surcharges.php`);
            const data = await response.json();
            this.surcharges = data.data.surcharges || [];
            this.renderSurcharges();
        } catch (error) {
            console.error('Error loading surcharges:', error);
        }
    }

    renderSurcharges() {
        const tbody = document.getElementById('surchargesTable')?.querySelector('tbody');
        if (!tbody) return;

        tbody.innerHTML = this.surcharges.map(surcharge => `
            <tr>
                <td>${surcharge.name}</td>
                <td>${surcharge.start_day}.${String(surcharge.start_month).padStart(2, '0')}</td>
                <td>${surcharge.end_day}.${String(surcharge.end_month).padStart(2, '0')}</td>
                <td>${surcharge.surcharge_percent}%</td>
                <td>
                    <button class="btn btn-xs btn-warning" onclick="pricingEngine.editSurcharge(${surcharge.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-xs btn-danger" onclick="pricingEngine.deleteSurcharge(${surcharge.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    openSurchargeModal(surchargeId = null) {
        const modal = document.getElementById('surchargeModal');
        if (surchargeId) {
            const surcharge = this.surcharges.find(s => s.id === surchargeId);
            if (surcharge) {
                document.getElementById('surcharge_id').value = surcharge.id;
                document.getElementById('surcharge_name').value = surcharge.name;
                document.getElementById('surcharge_start_month').value = surcharge.start_month;
                document.getElementById('surcharge_start_day').value = surcharge.start_day;
                document.getElementById('surcharge_end_month').value = surcharge.end_month;
                document.getElementById('surcharge_end_day').value = surcharge.end_day;
                document.getElementById('surcharge_percent').value = surcharge.surcharge_percent;
            }
        } else {
            document.getElementById('surcharge_id').value = '';
            document.getElementById('surcharge_name').value = '';
            document.getElementById('surcharge_start_month').value = 6;
            document.getElementById('surcharge_start_day').value = 1;
            document.getElementById('surcharge_end_month').value = 8;
            document.getElementById('surcharge_end_day').value = 31;
            document.getElementById('surcharge_percent').value = '';
        }
        $(modal).modal('show');
    }

    editSurcharge(surchargeId) {
        this.openSurchargeModal(surchargeId);
    }

    async saveSurcharge() {
        const form = document.getElementById('surchargeForm');
        const formData = new FormData(form);

        try {
            const response = await fetch(`${API_BASE}/surcharges.php`, {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();
            if (!data.success) throw new Error(data.message);

            $(document.getElementById('surchargeModal')).modal('hide');
            this.loadSurcharges();
        } catch (error) {
            alert('Fehler beim Speichern: ' + error.message);
        }
    }

    async deleteSurcharge(surchargeId) {
        if (!confirm('Saisonzuschlag wirklich löschen?')) return;

        try {
            const response = await fetch(`${API_BASE}/surcharges_delete.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': CSRF_TOKEN,
                },
                body: new URLSearchParams({ id: surchargeId }),
            });

            const data = await response.json();
            if (!data.success) throw new Error(data.message);

            this.loadSurcharges();
        } catch (error) {
            alert('Fehler beim Löschen: ' + error.message);
        }
    }

    // ===== BUNDLES =====
    async loadBundles() {
        try {
            const response = await fetch(`${API_BASE}/bundles.php`);
            const data = await response.json();
            this.bundles = data.data.bundles || [];
            this.renderBundles();
        } catch (error) {
            console.error('Error loading bundles:', error);
        }
    }

    renderBundles() {
        const tbody = document.getElementById('bundlesTable')?.querySelector('tbody');
        if (!tbody) return;

        tbody.innerHTML = this.bundles.map(bundle => `
            <tr>
                <td><strong>${bundle.name}</strong></td>
                <td><small>${bundle.description || '-'}</small></td>
                <td>${this.formatCurrency(bundle.bundle_price_per_day)}</td>
                <td>-</td>
                <td>
                    <span class="badge badge-${bundle.is_active ? 'success' : 'secondary'}">
                        ${bundle.is_active ? 'Aktiv' : 'Inaktiv'}
                    </span>
                </td>
                <td>
                    <button class="btn btn-xs btn-warning" onclick="pricingEngine.editBundle(${bundle.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-xs btn-danger" onclick="pricingEngine.deleteBundle(${bundle.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    openBundleModal(bundleId = null) {
        const modal = document.getElementById('bundleModal');
        document.getElementById('bundleItemsTable').querySelector('tbody').innerHTML = '';

        if (bundleId) {
            // Load bundle and populate modal
            const bundle = this.bundles.find(b => b.id === bundleId);
            if (bundle) {
                document.getElementById('bundle_id').value = bundle.id;
                document.getElementById('bundle_name').value = bundle.name;
                document.getElementById('bundle_desc').value = bundle.description;
                document.getElementById('bundle_price').value = bundle.bundle_price_per_day;
                document.getElementById('bundle_active').checked = bundle.is_active;
                // Items would be fetched from server
            }
        } else {
            document.getElementById('bundle_id').value = '';
            document.getElementById('bundle_name').value = '';
            document.getElementById('bundle_desc').value = '';
            document.getElementById('bundle_price').value = '';
            document.getElementById('bundle_active').checked = true;
        }
        $(modal).modal('show');
    }

    editBundle(bundleId) {
        this.openBundleModal(bundleId);
    }

    addBundleItemRow() {
        const tbody = document.getElementById('bundleItemsTable')?.querySelector('tbody');
        if (!tbody) return;

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <select class="form-control form-control-sm asset-select" required>
                    <option value="">-- Artikel auswählen --</option>
                </select>
            </td>
            <td><input type="number" class="form-control form-control-sm" value="1" min="1" required></td>
            <td><button type="button" class="btn btn-xs btn-danger" onclick="this.closest('tr').remove()">
                <i class="fas fa-trash"></i>
            </button></td>
        `;
        tbody.appendChild(row);
    }

    async saveBundle() {
        const form = document.getElementById('bundleForm');
        const formData = new FormData(form);

        const items = [];
        document.getElementById('bundleItemsTable').querySelectorAll('tbody tr').forEach(row => {
            const assetTypeId = row.querySelector('select').value;
            const quantity = row.querySelector('input[type="number"]').value;
            if (assetTypeId) {
                items.push({ asset_type_id: assetTypeId, quantity: quantity });
            }
        });

        formData.append('items', JSON.stringify(items));

        try {
            const response = await fetch(`${API_BASE}/bundles.php`, {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();
            if (!data.success) throw new Error(data.message);

            $(document.getElementById('bundleModal')).modal('hide');
            this.loadBundles();
        } catch (error) {
            alert('Fehler beim Speichern: ' + error.message);
        }
    }

    async deleteBundle(bundleId) {
        if (!confirm('Paket wirklich löschen?')) return;

        try {
            const response = await fetch(`${API_BASE}/bundles_delete.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': CSRF_TOKEN,
                },
                body: new URLSearchParams({ id: bundleId }),
            });

            const data = await response.json();
            if (!data.success) throw new Error(data.message);

            this.loadBundles();
        } catch (error) {
            alert('Fehler beim Löschen: ' + error.message);
        }
    }

    // ===== CUSTOMER PRICING =====
    async loadCustomerPricing(clientId) {
        if (!clientId) {
            document.getElementById('customerPricingContent').style.display = 'none';
            return;
        }

        this.currentCustomerClient = clientId;

        try {
            const response = await fetch(`${API_BASE}/customer_list.php?client_id=${clientId}`);
            const data = await response.json();

            if (data.success && data.data.list) {
                const list = data.data.list;
                document.getElementById('customer_global_discount').value = list.discount_percent || '';
                this.currentCustomerItems = list.items || [];
            } else {
                document.getElementById('customer_global_discount').value = '';
                this.currentCustomerItems = [];
            }

            this.renderCustomerItems();
            document.getElementById('customerPricingContent').style.display = '';
        } catch (error) {
            console.error('Error loading customer pricing:', error);
        }
    }

    renderCustomerItems() {
        const tbody = document.getElementById('customerItemsTable')?.querySelector('tbody');
        if (!tbody) return;

        tbody.innerHTML = this.currentCustomerItems.map(item => `
            <tr>
                <td>${this.getAssetTypeName(item.asset_type_id)}</td>
                <td>${this.formatCurrency(item.custom_price_per_day)}</td>
                <td>
                    <button class="btn btn-xs btn-danger" onclick="pricingEngine.deleteCustomerItem(${item.asset_type_id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    addCustomerItemRow() {
        const tbody = document.getElementById('customerItemsTable')?.querySelector('tbody');
        if (!tbody) return;

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <select class="form-control form-control-sm asset-select" data-asset-select required>
                    <option value="">-- Artikel auswählen --</option>
                </select>
            </td>
            <td><input type="number" class="form-control form-control-sm" step="0.01" min="0" required></td>
            <td><button type="button" class="btn btn-xs btn-danger" onclick="this.closest('tr').remove()">
                <i class="fas fa-trash"></i>
            </button></td>
        `;
        tbody.appendChild(row);
    }

    deleteCustomerItem(assetTypeId) {
        this.currentCustomerItems = this.currentCustomerItems.filter(item => item.asset_type_id !== assetTypeId);
        this.renderCustomerItems();
    }

    async saveCustomerPricing() {
        if (!this.currentCustomerClient) {
            alert('Bitte Kunde auswählen');
            return;
        }

        const items = [];
        document.getElementById('customerItemsTable').querySelectorAll('tbody tr').forEach(row => {
            const assetTypeId = row.querySelector('select').value;
            const price = row.querySelector('input[type="number"]').value;
            if (assetTypeId && price) {
                items.push({
                    asset_type_id: assetTypeId,
                    custom_price_per_day: parseFloat(price),
                });
            }
        });

        if (items.length === 0) {
            alert('Bitte mindestens einen Artikel hinzufügen');
            return;
        }

        try {
            const response = await fetch(`${API_BASE}/customer_list.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': CSRF_TOKEN,
                },
                body: new URLSearchParams({
                    client_id: this.currentCustomerClient,
                    items: JSON.stringify(items),
                    global_discount: document.getElementById('customer_global_discount').value || '',
                }),
            });

            const data = await response.json();
            if (!data.success) throw new Error(data.message);

            alert('Kundenpreisliste gespeichert');
            this.loadCustomerPricing(this.currentCustomerClient);
        } catch (error) {
            alert('Fehler beim Speichern: ' + error.message);
        }
    }

    // ===== HELPERS =====
    getAssetTypeName(assetTypeId) {
        // Would be populated from page data
        return `Artikel ${assetTypeId}`;
    }

    formatCurrency(value) {
        return parseFloat(value).toLocaleString('de-DE', {
            style: 'currency',
            currency: 'EUR',
        });
    }
}

// Initialize when DOM is ready
let pricingEngine;
document.addEventListener('DOMContentLoaded', () => {
    pricingEngine = new PricingEngine();
});
