-- =====================================================================
--  SKEMA DATABASE POS TOKO ATK - CV PL MAJU
--  Versi 1.2 - PostgreSQL 15/16 (jalankan di container Docker pada Synology DS923+)
--
--  Prinsip desain
--   1. Stok = buku besar pergerakan (stock_movements), tidak pernah diedit/dihapus.
--      stock_balances hanya ringkasan yang diperbarui oleh apply_stock_movement().
--   2. Harga pokok = rata-rata bergerak (moving average) per produk per lokasi.
--      Barang keluar selalu pada rata-rata saat itu; barang masuk menghitung ulang rata-rata.
--   3. Semua stok disimpan dalam SATUAN DASAR produk (mis. pcs). Satuan alternatif
--      (mis. box) hanya dikonversi saat transaksi: qty_base = qty * alt_unit_qty.
--   4. Uang: numeric (bukan float). Harga pokok per satuan dasar 4 desimal, nilai uang 2 desimal.
--   5. Nota yang sudah selesai tidak dihapus: gunakan void_sale() (dengan alasan dan log).
--   6. Waktu disimpan timestamptz; laporan harian memakai zona Asia/Jakarta.
-- =====================================================================

CREATE SCHEMA IF NOT EXISTS pos;
SET search_path = pos, public;

-- ---------- Tipe ----------
CREATE TYPE user_role     AS ENUM ('admin','kasir','pemilik');
CREATE TYPE uom_kind      AS ENUM ('dasar','alternatif');
CREATE TYPE doc_status    AS ENUM ('selesai','void');
CREATE TYPE pay_method    AS ENUM ('tunai','transfer','qris','lainnya');
CREATE TYPE movement_type AS ENUM ('OPENING','PURCHASE','SALE','SALE_VOID','ADJUSTMENT','TRANSFER_IN','TRANSFER_OUT','RETURN_SUPPLIER');

-- ---------- Pengguna & pengaturan ----------
CREATE TABLE users (                 -- kolom disesuaikan dengan Laravel/Filament
  id             bigserial PRIMARY KEY,
  name           text NOT NULL,
  email          text NOT NULL,               -- dipakai untuk login; boleh alamat fiktif, mis. indah@plmaju.local
  password       text NOT NULL,               -- hash dari Laravel, JANGAN simpan password asli
  role           user_role NOT NULL DEFAULT 'kasir',   -- naikkan ke 'admin'/'pemilik' secara manual
  is_active      boolean NOT NULL DEFAULT true,
  remember_token varchar(100),
  created_at     timestamptz NOT NULL DEFAULT now(),
  updated_at     timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX users_email_uq ON users (lower(email));

CREATE TABLE app_settings (key text PRIMARY KEY, value text NOT NULL, note text);
INSERT INTO app_settings VALUES
 ('allow_negative_stock','false','true = kasir boleh menjual walau stok sistem 0 (tidak disarankan)'),
 ('store_timezone','Asia/Jakarta','zona waktu laporan');

CREATE TABLE locations (            -- kanal/lokasi stok
  id   smallserial PRIMARY KEY,
  code text NOT NULL UNIQUE,
  name text NOT NULL
);
INSERT INTO locations(code,name) VALUES ('TOKO','Toko'),('SIIPLAH','Stok Kanal SIIPLAH');

CREATE TABLE price_tiers (
  id   smallserial PRIMARY KEY,
  code text NOT NULL UNIQUE,
  name text NOT NULL
);
INSERT INTO price_tiers(code,name) VALUES ('ECERAN','Eceran'),('SEKOLAH','Sekolah/Instansi'),('SIIPLAH','SIIPLAH');

-- ---------- Master produk ----------
CREATE TABLE categories (
  id   smallserial PRIMARY KEY,
  code text NOT NULL UNIQUE,          -- awalan SKU, mis. BOL
  name text NOT NULL UNIQUE
);

CREATE TABLE products (
  id           bigserial PRIMARY KEY,
  sku          text NOT NULL UNIQUE,  -- mis. BOL-0001
  barcode      text UNIQUE,
  name         text NOT NULL,
  category_id  smallint NOT NULL REFERENCES categories(id),
  base_unit    text NOT NULL,         -- pcs, lbr, box, pak
  alt_unit     text,                  -- mis. box
  alt_unit_qty numeric(12,3),         -- isi 1 satuan alternatif dalam satuan dasar
  min_stock    numeric(14,3) NOT NULL DEFAULT 0 CHECK (min_stock >= 0),
  is_active    boolean NOT NULL DEFAULT true,
  notes        text,
  created_at   timestamptz NOT NULL DEFAULT now(),
  updated_at   timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT alt_unit_pair CHECK ((alt_unit IS NULL) = (alt_unit_qty IS NULL)),
  CONSTRAINT alt_unit_pos  CHECK (alt_unit_qty IS NULL OR alt_unit_qty > 0)
);
CREATE INDEX products_name_idx ON products (lower(name));
CREATE INDEX products_cat_idx  ON products (category_id);

CREATE TABLE product_prices (
  id         bigserial PRIMARY KEY,
  product_id bigint   NOT NULL REFERENCES products(id),
  tier_id    smallint NOT NULL REFERENCES price_tiers(id),
  unit_kind  uom_kind NOT NULL DEFAULT 'dasar',
  price      numeric(14,2) NOT NULL CHECK (price >= 0),
  UNIQUE (product_id, tier_id, unit_kind)
);

-- ---------- Stok ----------
CREATE TABLE stock_balances (
  product_id  bigint   NOT NULL REFERENCES products(id),
  location_id smallint NOT NULL REFERENCES locations(id),
  qty         numeric(14,3) NOT NULL DEFAULT 0,
  avg_cost    numeric(14,4) NOT NULL DEFAULT 0 CHECK (avg_cost >= 0),
  updated_at  timestamptz NOT NULL DEFAULT now(),
  PRIMARY KEY (product_id, location_id)
);

CREATE TABLE stock_movements (
  id            bigserial PRIMARY KEY,
  product_id    bigint   NOT NULL REFERENCES products(id),
  location_id   smallint NOT NULL REFERENCES locations(id),
  movement_type movement_type NOT NULL,
  qty_change    numeric(14,3) NOT NULL CHECK (qty_change <> 0),   -- + masuk, - keluar (satuan dasar)
  unit_cost     numeric(14,4) NOT NULL CHECK (unit_cost >= 0),
  value_change  numeric(16,2) GENERATED ALWAYS AS (round(qty_change * unit_cost, 2)) STORED,
  moved_at      timestamptz NOT NULL DEFAULT now(),
  user_id       bigint REFERENCES users(id),
  ref_type      text,                 -- 'sale','purchase','opname',...
  ref_id        bigint,
  note          text
);
CREATE INDEX sm_prod_loc_time_idx ON stock_movements (product_id, location_id, moved_at);
CREATE INDEX sm_ref_idx           ON stock_movements (ref_type, ref_id);
CREATE INDEX sm_time_idx          ON stock_movements (moved_at);

-- ---------- Pelanggan & pemasok ----------
CREATE TABLE customers (
  id           bigserial PRIMARY KEY,
  code         text NOT NULL UNIQUE,  -- selaraskan dgn kode di aplikasi percetakan (CUST-01)
  name         text NOT NULL,
  customer_type text NOT NULL DEFAULT 'umum' CHECK (customer_type IN ('umum','instansi')),
  phone        text,
  address      text,
  allow_credit boolean NOT NULL DEFAULT false,
  credit_limit numeric(14,2) NOT NULL DEFAULT 0 CHECK (credit_limit >= 0),  -- 0 = tanpa batas
  credit_days  integer NOT NULL DEFAULT 0 CHECK (credit_days >= 0),
  is_active    boolean NOT NULL DEFAULT true,
  notes        text
);

CREATE TABLE suppliers (
  id        bigserial PRIMARY KEY,
  code      text NOT NULL UNIQUE,
  name      text NOT NULL,
  phone     text,
  address   text,
  is_active boolean NOT NULL DEFAULT true,
  notes     text
);

-- ---------- Penomoran dokumen ----------
CREATE TABLE doc_counters (
  prefix   text NOT NULL,
  doc_date date NOT NULL,
  last_no  integer NOT NULL DEFAULT 0,
  PRIMARY KEY (prefix, doc_date)
);

CREATE FUNCTION next_doc_no(p_prefix text, p_date date DEFAULT current_date) RETURNS text
LANGUAGE plpgsql AS $$
DECLARE n integer;
BEGIN
  INSERT INTO doc_counters AS d (prefix, doc_date, last_no) VALUES (p_prefix, p_date, 1)
  ON CONFLICT (prefix, doc_date) DO UPDATE SET last_no = d.last_no + 1
  RETURNING d.last_no INTO n;
  RETURN p_prefix || '-' || to_char(p_date,'YYMMDD') || '-' || lpad(n::text,4,'0');
END $$;
-- Contoh: next_doc_no('ATK') -> ATK-261010-0001. Prefix: ATK=penjualan, PB=pembelian, KWT=pembayaran, SO=opname.

-- ---------- Shift kasir ----------
CREATE TABLE cash_shifts (
  id            bigserial PRIMARY KEY,
  cashier_id    bigint   NOT NULL REFERENCES users(id),
  location_id   smallint NOT NULL REFERENCES locations(id),
  opened_at     timestamptz NOT NULL DEFAULT now(),
  opening_cash  numeric(14,2) NOT NULL DEFAULT 0 CHECK (opening_cash >= 0),
  closed_at     timestamptz,
  expected_cash numeric(14,2),
  counted_cash  numeric(14,2),
  difference    numeric(14,2) GENERATED ALWAYS AS (counted_cash - expected_cash) STORED,
  note          text
);
CREATE UNIQUE INDEX one_open_shift_per_cashier ON cash_shifts (cashier_id) WHERE closed_at IS NULL;

-- ---------- Penjualan ----------
CREATE TABLE sales (
  id             bigserial PRIMARY KEY,
  doc_no         text NOT NULL UNIQUE,
  sale_date      timestamptz NOT NULL DEFAULT now(),
  location_id    smallint NOT NULL REFERENCES locations(id),
  tier_id        smallint NOT NULL REFERENCES price_tiers(id),
  customer_id    bigint REFERENCES customers(id),        -- NULL = pembeli umum
  cashier_id     bigint NOT NULL REFERENCES users(id),
  shift_id       bigint REFERENCES cash_shifts(id),
  subtotal       numeric(14,2) NOT NULL DEFAULT 0 CHECK (subtotal >= 0),
  discount       numeric(14,2) NOT NULL DEFAULT 0 CHECK (discount >= 0),
  total          numeric(14,2) GENERATED ALWAYS AS (subtotal - discount) STORED,
  paid_total     numeric(14,2) NOT NULL DEFAULT 0 CHECK (paid_total >= 0),   -- dijaga trigger
  payment_status text GENERATED ALWAYS AS (
                   CASE WHEN paid_total >= subtotal - discount THEN 'lunas'
                        WHEN paid_total > 0 THEN 'sebagian' ELSE 'belum' END) STORED,
  due_date       date,                                    -- diisi bila tempo
  status         doc_status NOT NULL DEFAULT 'selesai',
  void_reason    text,
  voided_at      timestamptz,
  voided_by      bigint REFERENCES users(id),
  note           text,
  CONSTRAINT discount_le_subtotal CHECK (discount <= subtotal),
  CONSTRAINT paid_le_total        CHECK (paid_total <= subtotal - discount),
  CONSTRAINT credit_needs_customer CHECK (due_date IS NULL OR customer_id IS NOT NULL),
  CONSTRAINT void_needs_reason    CHECK (status = 'selesai' OR (void_reason IS NOT NULL AND voided_at IS NOT NULL))
);
CREATE INDEX sales_date_idx     ON sales (sale_date);
CREATE INDEX sales_customer_idx ON sales (customer_id) WHERE customer_id IS NOT NULL;
CREATE INDEX sales_open_ar_idx  ON sales (customer_id, due_date) WHERE status='selesai' AND payment_status <> 'lunas';

CREATE TABLE sale_items (
  id             bigserial PRIMARY KEY,
  sale_id        bigint NOT NULL REFERENCES sales(id),
  product_id     bigint NOT NULL REFERENCES products(id),
  unit_kind      uom_kind NOT NULL DEFAULT 'dasar',
  qty            numeric(14,3) NOT NULL CHECK (qty > 0),
  qty_base       numeric(14,3) NOT NULL CHECK (qty_base > 0),     -- qty x isi satuan (diisi aplikasi)
  unit_price     numeric(14,2) NOT NULL CHECK (unit_price >= 0),
  discount       numeric(14,2) NOT NULL DEFAULT 0 CHECK (discount >= 0),
  line_total     numeric(14,2) GENERATED ALWAYS AS (round(qty * unit_price - discount, 2)) STORED,
  unit_cost_base numeric(14,4) NOT NULL DEFAULT 0,                -- snapshot HPP, diisi post_sale_stock()
  cogs           numeric(16,2) GENERATED ALWAYS AS (round(qty_base * unit_cost_base, 2)) STORED
);
CREATE INDEX sale_items_sale_idx ON sale_items (sale_id);
CREATE INDEX sale_items_prod_idx ON sale_items (product_id);

-- ---------- Pembayaran (mendukung bayar bertahap & satu pembayaran untuk banyak nota) ----------
CREATE TABLE payments (
  id          bigserial PRIMARY KEY,
  doc_no      text NOT NULL UNIQUE,
  customer_id bigint REFERENCES customers(id),
  method      pay_method NOT NULL,
  amount      numeric(14,2) NOT NULL CHECK (amount > 0),
  paid_at     timestamptz NOT NULL DEFAULT now(),
  shift_id    bigint REFERENCES cash_shifts(id),
  user_id     bigint NOT NULL REFERENCES users(id),
  reference   text,
  status      doc_status NOT NULL DEFAULT 'selesai',
  note        text
);
CREATE TABLE payment_allocations (
  id         bigserial PRIMARY KEY,
  payment_id bigint NOT NULL REFERENCES payments(id),
  sale_id    bigint NOT NULL REFERENCES sales(id),
  amount     numeric(14,2) NOT NULL CHECK (amount > 0),
  UNIQUE (payment_id, sale_id)
);
CREATE INDEX pa_sale_idx ON payment_allocations (sale_id);

-- ---------- Pembelian ----------
CREATE TABLE purchases (
  id            bigserial PRIMARY KEY,
  doc_no        text NOT NULL UNIQUE,
  supplier_id   bigint REFERENCES suppliers(id),
  location_id   smallint NOT NULL REFERENCES locations(id),
  purchase_date date NOT NULL DEFAULT current_date,
  supplier_invoice_no text,
  total         numeric(14,2) NOT NULL DEFAULT 0,
  status        doc_status NOT NULL DEFAULT 'selesai',
  created_by    bigint NOT NULL REFERENCES users(id),
  note          text,
  created_at    timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE purchase_items (
  id             bigserial PRIMARY KEY,
  purchase_id    bigint NOT NULL REFERENCES purchases(id),
  product_id     bigint NOT NULL REFERENCES products(id),
  unit_kind      uom_kind NOT NULL DEFAULT 'dasar',
  qty            numeric(14,3) NOT NULL CHECK (qty > 0),
  qty_base       numeric(14,3) NOT NULL CHECK (qty_base > 0),
  unit_cost_base numeric(14,4) NOT NULL CHECK (unit_cost_base >= 0),   -- harga beli per satuan dasar
  line_total     numeric(14,2) GENERATED ALWAYS AS (round(qty_base * unit_cost_base, 2)) STORED
);
CREATE INDEX purchase_items_purchase_idx ON purchase_items (purchase_id);

-- ---------- Stok opname ----------
CREATE TABLE stock_opnames (
  id          bigserial PRIMARY KEY,
  doc_no      text NOT NULL UNIQUE,
  location_id smallint NOT NULL REFERENCES locations(id),
  opname_date date NOT NULL DEFAULT current_date,
  status      text NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','selesai')),
  created_by  bigint NOT NULL REFERENCES users(id),
  note        text
);
CREATE TABLE stock_opname_items (
  id          bigserial PRIMARY KEY,
  opname_id   bigint NOT NULL REFERENCES stock_opnames(id),
  product_id  bigint NOT NULL REFERENCES products(id),
  system_qty  numeric(14,3) NOT NULL,
  counted_qty numeric(14,3) NOT NULL CHECK (counted_qty >= 0),
  diff_qty    numeric(14,3) GENERATED ALWAYS AS (counted_qty - system_qty) STORED,
  UNIQUE (opname_id, product_id)
);

-- ---------- Log audit ----------
CREATE TABLE audit_log (
  id         bigserial PRIMARY KEY,
  at         timestamptz NOT NULL DEFAULT now(),
  user_id    bigint REFERENCES users(id),
  action     text NOT NULL,          -- 'void_sale','price_change','login',...
  table_name text,
  record_id  bigint,
  old_data   jsonb,
  new_data   jsonb,
  note       text
);
CREATE INDEX audit_at_idx ON audit_log (at);

-- =====================================================================
--  FUNGSI INTI
-- =====================================================================

-- Satu-satunya jalan mengubah stok. Mengunci baris saldo (FOR UPDATE) agar aman bila
-- dua kasir menjual barang yang sama bersamaan. Mengembalikan harga pokok per satuan dasar yang dipakai.
CREATE FUNCTION apply_stock_movement(
  p_product bigint, p_location integer, p_type movement_type, p_qty numeric,
  p_unit_cost numeric DEFAULT NULL, p_user bigint DEFAULT NULL,
  p_ref_type text DEFAULT NULL, p_ref_id bigint DEFAULT NULL, p_note text DEFAULT NULL,
  p_moved_at timestamptz DEFAULT now()
) RETURNS numeric
LANGUAGE plpgsql AS $$
DECLARE v_qty numeric; v_avg numeric; v_cost numeric; v_new_qty numeric; v_new_avg numeric; v_allow_neg boolean;
BEGIN
  IF p_qty IS NULL OR p_qty = 0 THEN RAISE EXCEPTION 'Qty pergerakan stok tidak boleh 0'; END IF;
  IF p_type IN ('OPENING','PURCHASE','SALE_VOID','TRANSFER_IN') AND p_qty < 0 THEN
    RAISE EXCEPTION 'Tipe % harus bernilai positif', p_type; END IF;
  IF p_type IN ('SALE','TRANSFER_OUT','RETURN_SUPPLIER') AND p_qty > 0 THEN
    RAISE EXCEPTION 'Tipe % harus bernilai negatif', p_type; END IF;
  IF p_type IN ('OPENING','PURCHASE') AND p_unit_cost IS NULL THEN
    RAISE EXCEPTION 'Tipe % wajib menyertakan harga pokok', p_type; END IF;

  INSERT INTO stock_balances (product_id, location_id) VALUES (p_product, p_location)
  ON CONFLICT DO NOTHING;
  SELECT qty, avg_cost INTO v_qty, v_avg
    FROM stock_balances WHERE product_id = p_product AND location_id = p_location FOR UPDATE;

  v_new_qty := v_qty + p_qty;
  IF p_qty < 0 THEN
    SELECT (value = 'true') INTO v_allow_neg FROM app_settings WHERE key = 'allow_negative_stock';
    IF v_new_qty < 0 AND NOT COALESCE(v_allow_neg,false) THEN
      RAISE EXCEPTION 'Stok tidak cukup (produk id %, tersedia %, diminta %)', p_product, v_qty, -p_qty
        USING ERRCODE = 'check_violation';
    END IF;
    v_cost := v_avg;                 -- barang keluar selalu pada rata-rata; rata-rata tidak berubah
    v_new_avg := v_avg;
  ELSE
    v_cost := COALESCE(p_unit_cost, v_avg);
    IF v_new_qty > 0 THEN
      v_new_avg := round((GREATEST(v_qty,0) * v_avg + p_qty * v_cost) / (GREATEST(v_qty,0) + p_qty), 4);
    ELSE
      v_new_avg := v_cost;
    END IF;
  END IF;

  UPDATE stock_balances SET qty = v_new_qty, avg_cost = v_new_avg, updated_at = now()
   WHERE product_id = p_product AND location_id = p_location;
  INSERT INTO stock_movements (product_id, location_id, movement_type, qty_change, unit_cost, moved_at, user_id, ref_type, ref_id, note)
  VALUES (p_product, p_location, p_type, p_qty, v_cost, p_moved_at, p_user, p_ref_type, p_ref_id, p_note);
  RETURN v_cost;
END $$;

-- Posting stok untuk satu nota penjualan (panggil dalam transaksi yang sama dengan INSERT sales/sale_items).
CREATE FUNCTION post_sale_stock(p_sale bigint, p_user bigint) RETURNS void
LANGUAGE plpgsql AS $$
DECLARE s sales%ROWTYPE; it record; v_cost numeric;
BEGIN
  SELECT * INTO s FROM sales WHERE id = p_sale FOR UPDATE;
  IF NOT FOUND THEN RAISE EXCEPTION 'Nota % tidak ditemukan', p_sale; END IF;
  IF s.status <> 'selesai' THEN RAISE EXCEPTION 'Nota % berstatus void', s.doc_no; END IF;
  IF EXISTS (SELECT 1 FROM stock_movements WHERE ref_type='sale' AND ref_id=p_sale AND movement_type='SALE') THEN
    RAISE EXCEPTION 'Stok nota % sudah diposting', s.doc_no; END IF;
  FOR it IN SELECT id, product_id, qty_base FROM sale_items WHERE sale_id = p_sale ORDER BY product_id, id LOOP
    v_cost := apply_stock_movement(it.product_id, s.location_id, 'SALE', -it.qty_base, NULL, p_user, 'sale', p_sale, s.doc_no, s.sale_date);
    UPDATE sale_items SET unit_cost_base = v_cost WHERE id = it.id;
  END LOOP;
END $$;

-- Void nota: stok kembali pada harga pokok saat dijual. Nota yang sudah ada pembayaran aktif
-- harus dibatalkan pembayarannya dulu (payments.status = 'void').
CREATE FUNCTION void_sale(p_sale bigint, p_user bigint, p_reason text) RETURNS void
LANGUAGE plpgsql AS $$
DECLARE s sales%ROWTYPE; it record;
BEGIN
  IF p_reason IS NULL OR length(trim(p_reason)) < 3 THEN RAISE EXCEPTION 'Alasan void wajib diisi'; END IF;
  SELECT * INTO s FROM sales WHERE id = p_sale FOR UPDATE;
  IF NOT FOUND THEN RAISE EXCEPTION 'Nota % tidak ditemukan', p_sale; END IF;
  IF s.status = 'void' THEN RAISE EXCEPTION 'Nota % sudah void', s.doc_no; END IF;
  IF s.paid_total > 0 THEN RAISE EXCEPTION 'Nota % sudah ada pembayaran Rp%; batalkan pembayaran lebih dulu', s.doc_no, s.paid_total; END IF;
  FOR it IN SELECT product_id, qty_base, unit_cost_base FROM sale_items WHERE sale_id = p_sale ORDER BY product_id, id LOOP
    PERFORM apply_stock_movement(it.product_id, s.location_id, 'SALE_VOID', it.qty_base, it.unit_cost_base, p_user, 'sale', p_sale, 'Void '||s.doc_no);
  END LOOP;
  UPDATE sales SET status='void', void_reason=p_reason, voided_at=now(), voided_by=p_user WHERE id = p_sale;
  INSERT INTO audit_log(user_id, action, table_name, record_id, old_data, new_data, note)
  VALUES (p_user, 'void_sale', 'sales', p_sale, to_jsonb(s), NULL, p_reason);
END $$;

CREATE FUNCTION post_purchase_stock(p_purchase bigint, p_user bigint) RETURNS void
LANGUAGE plpgsql AS $$
DECLARE h purchases%ROWTYPE; it record;
BEGIN
  SELECT * INTO h FROM purchases WHERE id = p_purchase FOR UPDATE;
  IF NOT FOUND THEN RAISE EXCEPTION 'Pembelian % tidak ditemukan', p_purchase; END IF;
  IF EXISTS (SELECT 1 FROM stock_movements WHERE ref_type='purchase' AND ref_id=p_purchase) THEN
    RAISE EXCEPTION 'Pembelian % sudah diposting', h.doc_no; END IF;
  FOR it IN SELECT product_id, qty_base, unit_cost_base FROM purchase_items WHERE purchase_id = p_purchase ORDER BY product_id, id LOOP
    PERFORM apply_stock_movement(it.product_id, h.location_id, 'PURCHASE', it.qty_base, it.unit_cost_base, p_user, 'purchase', p_purchase, h.doc_no,
                                 h.purchase_date::timestamptz);
  END LOOP;
  UPDATE purchases SET total = (SELECT COALESCE(sum(line_total),0) FROM purchase_items WHERE purchase_id = p_purchase) WHERE id = p_purchase;
END $$;

-- Selisih opname dibukukan sebagai ADJUSTMENT pada harga pokok rata-rata.
-- Catatan: system_qty diisi saat penghitungan; lakukan opname di luar jam transaksi atau bekukan penjualan.
CREATE FUNCTION post_opname(p_opname bigint, p_user bigint) RETURNS void
LANGUAGE plpgsql AS $$
DECLARE o stock_opnames%ROWTYPE; it record;
BEGIN
  SELECT * INTO o FROM stock_opnames WHERE id = p_opname FOR UPDATE;
  IF NOT FOUND THEN RAISE EXCEPTION 'Opname % tidak ditemukan', p_opname; END IF;
  IF o.status <> 'draft' THEN RAISE EXCEPTION 'Opname % sudah selesai', o.doc_no; END IF;
  FOR it IN SELECT product_id, diff_qty FROM stock_opname_items WHERE opname_id = p_opname AND diff_qty <> 0 ORDER BY product_id, id LOOP
    PERFORM apply_stock_movement(it.product_id, o.location_id, 'ADJUSTMENT', it.diff_qty, NULL, p_user, 'opname', p_opname, o.doc_no);
  END LOOP;
  UPDATE stock_opnames SET status='selesai' WHERE id = p_opname;
END $$;

-- Tutup shift: kas yang seharusnya = kas awal + semua pembayaran TUNAI aktif pada shift itu.
CREATE FUNCTION close_shift(p_shift bigint, p_counted numeric) RETURNS numeric
LANGUAGE plpgsql AS $$
DECLARE v_exp numeric;
BEGIN
  SELECT cs.opening_cash + COALESCE((SELECT sum(amount) FROM payments p
          WHERE p.shift_id = cs.id AND p.method = 'tunai' AND p.status = 'selesai'),0)
    INTO v_exp FROM cash_shifts cs WHERE cs.id = p_shift AND cs.closed_at IS NULL FOR UPDATE;
  IF v_exp IS NULL THEN RAISE EXCEPTION 'Shift % tidak ditemukan atau sudah ditutup', p_shift; END IF;
  UPDATE cash_shifts SET closed_at = now(), expected_cash = v_exp, counted_cash = p_counted WHERE id = p_shift;
  RETURN p_counted - v_exp;
END $$;

-- =====================================================================
--  TRIGGER INTEGRITAS
-- =====================================================================

-- 1. Tabel catatan tidak boleh diubah/dihapus sembarangan
CREATE FUNCTION prevent_change() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'Tabel % tidak boleh di-% (gunakan void/penyesuaian)', TG_TABLE_NAME, lower(TG_OP); END $$;
CREATE TRIGGER stock_movements_immutable BEFORE UPDATE OR DELETE ON stock_movements FOR EACH ROW EXECUTE FUNCTION prevent_change();
CREATE TRIGGER sales_no_delete           BEFORE DELETE ON sales               FOR EACH ROW EXECUTE FUNCTION prevent_change();
CREATE TRIGGER sale_items_no_delete      BEFORE DELETE ON sale_items          FOR EACH ROW EXECUTE FUNCTION prevent_change();
CREATE TRIGGER payments_no_delete        BEFORE DELETE ON payments            FOR EACH ROW EXECUTE FUNCTION prevent_change();
CREATE TRIGGER pay_alloc_no_delete       BEFORE DELETE ON payment_allocations FOR EACH ROW EXECUTE FUNCTION prevent_change();
CREATE TRIGGER purchases_no_delete       BEFORE DELETE ON purchases           FOR EACH ROW EXECUTE FUNCTION prevent_change();

-- 2. paid_total pada nota selalu dihitung dari alokasi pembayaran aktif
CREATE FUNCTION recalc_paid(p_sale bigint) RETURNS void LANGUAGE sql AS $$
  UPDATE sales SET paid_total = COALESCE((
      SELECT sum(pa.amount) FROM payment_allocations pa JOIN payments p ON p.id = pa.payment_id
       WHERE pa.sale_id = p_sale AND p.status = 'selesai'), 0)
   WHERE id = p_sale;
$$;
CREATE FUNCTION trg_alloc_changed() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE v_amount numeric; v_alloc numeric;
BEGIN
  SELECT amount INTO v_amount FROM payments WHERE id = NEW.payment_id;
  SELECT sum(amount) INTO v_alloc FROM payment_allocations WHERE payment_id = NEW.payment_id;
  IF v_alloc > v_amount THEN
    RAISE EXCEPTION 'Total alokasi (%) melebihi jumlah pembayaran (%)', v_alloc, v_amount; END IF;
  PERFORM recalc_paid(NEW.sale_id);
  IF TG_OP = 'UPDATE' AND OLD.sale_id <> NEW.sale_id THEN PERFORM recalc_paid(OLD.sale_id); END IF;
  RETURN NULL;
END $$;
CREATE TRIGGER pay_alloc_changed AFTER INSERT OR UPDATE ON payment_allocations FOR EACH ROW EXECUTE FUNCTION trg_alloc_changed();

CREATE FUNCTION trg_payment_status() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE r record;
BEGIN
  FOR r IN SELECT DISTINCT sale_id FROM payment_allocations WHERE payment_id = NEW.id LOOP PERFORM recalc_paid(r.sale_id); END LOOP;
  RETURN NULL;
END $$;
CREATE TRIGGER payment_status_changed AFTER UPDATE OF status ON payments FOR EACH ROW
  WHEN (OLD.status IS DISTINCT FROM NEW.status) EXECUTE FUNCTION trg_payment_status();

-- 3. Verifikasi saat COMMIT: subtotal = jumlah baris, dan aturan tempo (pelanggan boleh tempo, batas kredit)
CREATE FUNCTION check_sale_integrity(p_sale bigint) RETURNS void LANGUAGE plpgsql AS $$
DECLARE s sales%ROWTYPE; c customers%ROWTYPE; v_lines numeric; v_open numeric;
BEGIN
  SELECT * INTO s FROM sales WHERE id = p_sale;
  IF NOT FOUND OR s.status <> 'selesai' THEN RETURN; END IF;
  SELECT COALESCE(sum(line_total),0) INTO v_lines FROM sale_items WHERE sale_id = p_sale;
  IF v_lines <> s.subtotal THEN
    RAISE EXCEPTION 'Nota %: subtotal (%) tidak sama dengan jumlah baris (%)', s.doc_no, s.subtotal, v_lines; END IF;
  IF s.due_date IS NOT NULL THEN
    SELECT * INTO c FROM customers WHERE id = s.customer_id;
    IF NOT c.allow_credit THEN RAISE EXCEPTION 'Pelanggan % tidak diizinkan tempo', c.name; END IF;
    IF c.credit_limit > 0 THEN
      SELECT COALESCE(sum(total - paid_total),0) INTO v_open FROM sales
       WHERE customer_id = c.id AND status = 'selesai' AND id <> s.id;
      IF v_open + (s.total - s.paid_total) > c.credit_limit THEN
        RAISE EXCEPTION 'Melebihi batas kredit % (piutang berjalan % + nota ini %)', c.credit_limit, v_open, s.total - s.paid_total; END IF;
    END IF;
  END IF;
END $$;
CREATE FUNCTION trg_sales_integrity() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN PERFORM check_sale_integrity(NEW.id); RETURN NULL; END $$;
CREATE FUNCTION trg_sale_items_integrity() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN PERFORM check_sale_integrity(NEW.sale_id); RETURN NULL; END $$;
CREATE CONSTRAINT TRIGGER sales_integrity AFTER INSERT OR UPDATE ON sales
  DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION trg_sales_integrity();
CREATE CONSTRAINT TRIGGER sale_items_integrity AFTER INSERT OR UPDATE ON sale_items
  DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION trg_sale_items_integrity();

-- =====================================================================
--  VIEW & LAPORAN
-- =====================================================================
CREATE VIEW v_stock_current AS
SELECT p.id AS product_id, p.sku, p.name, c.name AS category, l.code AS location, p.base_unit,
       b.qty, b.avg_cost, round(b.qty * b.avg_cost, 2) AS value,
       (b.qty <= 0) AS is_empty,
       (p.min_stock > 0 AND b.qty <= p.min_stock) AS is_low
FROM stock_balances b
JOIN products p   ON p.id = b.product_id
JOIN categories c ON c.id = p.category_id
JOIN locations l  ON l.id = b.location_id;

CREATE VIEW v_receivable AS
SELECT s.id AS sale_id, s.doc_no, s.sale_date, s.customer_id, cu.code AS customer_code, cu.name AS customer_name,
       s.total, s.paid_total, s.total - s.paid_total AS outstanding,
       COALESCE(s.due_date, (s.sale_date AT TIME ZONE 'Asia/Jakarta')::date + COALESCE(cu.credit_days,0)) AS eff_due_date,
       GREATEST(current_date - COALESCE(s.due_date, (s.sale_date AT TIME ZONE 'Asia/Jakarta')::date + COALESCE(cu.credit_days,0)), 0) AS days_overdue
FROM sales s LEFT JOIN customers cu ON cu.id = s.customer_id
WHERE s.status = 'selesai' AND s.total > s.paid_total;

CREATE VIEW v_ar_aging AS
SELECT customer_id, customer_code, customer_name,
       sum(outstanding)                                              AS total_piutang,
       sum(outstanding) FILTER (WHERE days_overdue = 0)              AS belum_jatuh_tempo,
       sum(outstanding) FILTER (WHERE days_overdue BETWEEN 1 AND 30) AS telat_1_30,
       sum(outstanding) FILTER (WHERE days_overdue BETWEEN 31 AND 60) AS telat_31_60,
       sum(outstanding) FILTER (WHERE days_overdue > 60)             AS telat_lebih_60
FROM v_receivable GROUP BY customer_id, customer_code, customer_name;

CREATE VIEW v_sales_daily AS
SELECT (s.sale_date AT TIME ZONE 'Asia/Jakarta')::date AS tanggal, l.code AS lokasi,
       count(*) AS jumlah_nota, sum(s.total) AS omzet, sum(x.cogs) AS hpp, sum(s.total) - sum(x.cogs) AS laba_kotor
FROM sales s
JOIN locations l ON l.id = s.location_id
JOIN LATERAL (SELECT COALESCE(sum(cogs),0) AS cogs FROM sale_items WHERE sale_id = s.id) x ON true
WHERE s.status = 'selesai'
GROUP BY 1, 2;

-- Laporan persediaan periodik, padanan format Excel "Laporan Persediaan" Anda.
-- Contoh: SELECT * FROM report_persediaan('2026-10-01','2026-10-31');
CREATE FUNCTION report_persediaan(p_from date, p_to date, p_location integer DEFAULT NULL)
RETURNS TABLE (sku text, nama text, kategori text, satuan text,
               awal_qty numeric, awal_nilai numeric, pembelian_qty numeric, pembelian_nilai numeric,
               penjualan_qty numeric, penjualan_hpp numeric, lain_qty numeric, lain_nilai numeric,
               akhir_qty numeric, akhir_nilai numeric)
LANGUAGE sql STABLE AS $$
  WITH m AS (
    SELECT sm.product_id, sm.movement_type, sm.qty_change, sm.value_change,
           (sm.moved_at AT TIME ZONE 'Asia/Jakarta')::date AS d
    FROM stock_movements sm WHERE p_location IS NULL OR sm.location_id = p_location
  ), a AS (
    SELECT product_id,
      COALESCE(sum(qty_change)   FILTER (WHERE d < p_from),0) AS aq,
      COALESCE(sum(value_change) FILTER (WHERE d < p_from),0) AS av,
      COALESCE(sum(qty_change)   FILTER (WHERE d BETWEEN p_from AND p_to AND movement_type = 'PURCHASE'),0) AS bq,
      COALESCE(sum(value_change) FILTER (WHERE d BETWEEN p_from AND p_to AND movement_type = 'PURCHASE'),0) AS bv,
      COALESCE(-sum(qty_change)   FILTER (WHERE d BETWEEN p_from AND p_to AND movement_type IN ('SALE','SALE_VOID')),0) AS jq,
      COALESCE(-sum(value_change) FILTER (WHERE d BETWEEN p_from AND p_to AND movement_type IN ('SALE','SALE_VOID')),0) AS jv,
      COALESCE(sum(qty_change)   FILTER (WHERE d BETWEEN p_from AND p_to AND movement_type NOT IN ('PURCHASE','SALE','SALE_VOID')),0) AS lq,
      COALESCE(sum(value_change) FILTER (WHERE d BETWEEN p_from AND p_to AND movement_type NOT IN ('PURCHASE','SALE','SALE_VOID')),0) AS lv
    FROM m WHERE d <= p_to GROUP BY product_id
  )
  SELECT p.sku, p.name, c.name, p.base_unit, a.aq, a.av, a.bq, a.bv, a.jq, a.jv, a.lq, a.lv,
         a.aq + a.bq - a.jq + a.lq, a.av + a.bv - a.jv + a.lv
  FROM a JOIN products p ON p.id = a.product_id JOIN categories c ON c.id = p.category_id
  WHERE a.aq <> 0 OR a.bq <> 0 OR a.jq <> 0 OR a.lq <> 0
  ORDER BY c.name, p.name;
$$;

-- Integritas: stok di stock_balances harus sama dengan jumlah pergerakan (harus 0 baris).
CREATE VIEW v_stock_integrity_check AS
SELECT b.product_id, b.location_id, b.qty AS saldo, COALESCE(m.q,0) AS dari_pergerakan
FROM stock_balances b
LEFT JOIN (SELECT product_id, location_id, sum(qty_change) q FROM stock_movements GROUP BY 1,2) m
       ON m.product_id = b.product_id AND m.location_id = b.location_id
WHERE b.qty <> COALESCE(m.q,0);

-- =====================================================================
--  DATA AWAL: kategori (dari master produk bersih)
-- =====================================================================
INSERT INTO categories (code, name) VALUES
  ('BOL', 'BOLPOINT'),
  ('SPI', 'SPIDOL'),
  ('STB', 'STABILLO'),
  ('TRC', 'TRACPEN'),
  ('PNS', 'PENSIL'),
  ('PWC', 'PENSIL WARNA/CRAYON'),
  ('TPX', 'TIPEX'),
  ('PGH', 'PENGHAPUS'),
  ('CTG', 'CUTTER/GUNTING'),
  ('LEM', 'LEM'),
  ('ISL', 'ISOLASI/DOUBLE TAPE/LAKBAN'),
  ('BKU', 'BUKU'),
  ('KRT', 'FOLIO/KERTAS FOTO'),
  ('NTA', 'NOTA/KWITANSI'),
  ('NBK', 'NOTE BOOK'),
  ('MPP', 'MEMO PAPER PAD'),
  ('SMP', 'SAMPUL PLASTIK'),
  ('AMP', 'AMPLOP'),
  ('STM', 'STOPMAP'),
  ('BCM', 'BC PINDO/INDAH MIKA BENING'),
  ('PGR', 'PENGGARIS/JANGKA/MAL'),
  ('BCL', 'BINDER CLIP'),
  ('STP', 'STAPLES & ISI'),
  ('IDC', 'ID CARD/RING ROTAN'),
  ('STK', 'STICKY NOTE'),
  ('PST', 'POST IT/LABEL'),
  ('SPL', 'STAMPLE'),
  ('LLN', 'LAIN-LAIN (BELUM DIKATEGORIKAN)'),
  ('KOP', 'KOPI RONCALLI');

-- =====================================================================
--  CARA IMPOR AWAL (ringkas)
--   1. Muat sheet 'Master Produk' ke tabel products + product_prices (tier ECERAN).
--      products.alt_unit/alt_unit_qty dari kolom 'Satuan Alternatif'/'Isi per Satuan Alt.'.
--   2. Untuk tiap baris sheet 'Saldo Awal' (dibuat per kanal), panggil:
--        SELECT apply_stock_movement(<product_id>, <location_id>, 'OPENING', <qty>, <harga_pokok>,
--               <user_id>, 'opening', NULL, 'Saldo awal 30 Sep 2026', '2026-09-30 23:59+07');
--      (lewati baris qty = 0; harga pokok barang stok nol disimpan di catatan master)
--   3. Cek: SELECT * FROM v_stock_integrity_check;  -- harus kosong
--      Cek: SELECT sum(value) FROM v_stock_current; -- harus mendekati Rp8.060.141
-- =====================================================================
