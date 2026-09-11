#!/usr/bin/env python3
"""Generate CoffeePOS Vietnamese PO, MO, and WordPress JavaScript catalogs."""

from __future__ import annotations

import json
import re
import struct
import sys
import time
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
POT = ROOT / "languages" / "coffeepos.pot"
PO = ROOT / "languages" / "coffeepos-vi.po"
MO = ROOT / "languages" / "coffeepos-vi.mo"
SEPARATOR = "ZXQCOFFEEPOSSEPZXQ"

OVERRIDES = {
    "%d item": "%d món",
    "%d items": "%d món",
    "Add item": "Thêm món",
    "Add order note": "Thêm ghi chú đơn hàng",
    "Add to cart": "Thêm vào giỏ hàng",
    "Amount due": "Số tiền cần thanh toán",
    "Bank Transfer": "Chuyển khoản ngân hàng",
    "Bundled": "Đóng gói sẵn",
    "Cart": "Giỏ hàng",
    "Cash": "Tiền mặt",
    "Cashier": "Thu ngân",
    "Change: %s": "Tiền thừa: %s",
    "Checkout": "Thanh toán",
    "Complete checkout": "Hoàn tất thanh toán",
    "Confirm received & complete": "Xác nhận đã nhận tiền và hoàn tất",
    "Customer Display": "Màn hình khách hàng",
    "Dine-in": "Dùng tại chỗ",
    "Discount": "Giảm giá",
    "Edit item": "Sửa món",
    "Extra sugar": "Nhiều đường",
    "Guest": "Khách lẻ",
    "Guest customer": "Khách lẻ",
    "Interface font": "Font giao diện",
    "Item note": "Ghi chú món",
    "Kitchen Display": "Màn hình bếp",
    "Less ice": "Ít đá",
    "Less milk": "Ít sữa",
    "Less sugar": "Ít đường",
    "Member": "Hội viên",
    "No order note": "Không có ghi chú đơn hàng",
    "Order History": "Lịch sử đơn hàng",
    "Order Queue": "Hàng đợi đơn hàng",
    "Order note": "Ghi chú đơn hàng",
    "Payment pending": "Đang chờ thanh toán",
    "Payment successful": "Thanh toán thành công",
    "Print receipt": "In hóa đơn",
    "Quick notes": "Ghi chú nhanh",
    "Receipt": "Hóa đơn",
    "Reports": "Báo cáo",
    "Settings": "Cài đặt",
    "Shift Management": "Quản lý ca làm việc",
    "Shifts": "Ca làm việc",
    "Subtotal": "Tạm tính",
    "Takeaway": "Mang đi",
    "The bundled default works offline. Google Fonts choices require an internet connection on each POS display.": "Font mặc định đóng gói sẵn hoạt động ngoại tuyến. Các lựa chọn Google Fonts cần kết nối Internet trên mỗi màn hình POS.",
    "Thank you!": "Cảm ơn quý khách!",
    "Total": "Tổng cộng",
    "Update item": "Cập nhật món",
    "VietQR payment code": "Mã thanh toán VietQR",
    "Your cart is empty": "Giỏ hàng đang trống",
    "Your order": "Đơn hàng của bạn",
}

OVERRIDES.update({
    "Actions": "Hành động",
    "Active shift": "Ca đang mở",
    "Actual": "Thực tế",
    "Advanced": "Nâng cao",
    "After WooCommerce refunds": "Sau khi hoàn tiền trong WooCommerce",
    "All active": "Tất cả đang hoạt động",
    "Allow new-order sound on KDS": "Bật âm thanh khi KDS có đơn hàng mới",
    "A new cashier cart will be created using current prices and availability.": "Một giỏ hàng thu ngân mới sẽ được tạo theo giá và tình trạng hàng hiện tại.",
    "A table cannot be used with this service type.": "Không thể chọn bàn cho loại phục vụ này.",
    "Appearance": "Giao diện",
    "At least one payment method must remain enabled. VietQR is shown only on Customer Display.": "Phải bật ít nhất một phương thức thanh toán. VietQR chỉ hiển thị trên Màn hình khách hàng.",
    "Bank transfer is unavailable.": "Chuyển khoản ngân hàng không khả dụng.",
    "Cash received is below the authoritative order total.": "Tiền mặt nhận được thấp hơn tổng tiền chính xác của đơn hàng.",
    "Clear": "Xóa",
    "CoffeePOS": "CoffeePOS",
    "CoffeePOS home": "Trang chủ CoffeePOS",
    "CoffeePOS requires Composer autoload files. Run \"composer install\" inside the plugin directory.": "CoffeePOS cần các file autoload của Composer. Hãy chạy lệnh \"composer install\" trong thư mục plugin.",
    "Customer display": "Màn hình khách hàng",
    "Customer display: Connected": "Màn hình khách: Đã kết nối",
    "Customer display: Connecting…": "Màn hình khách: Đang kết nối…",
    "Customer display: Not connected": "Màn hình khách: Chưa kết nối",
    "Customer Display synchronization is unavailable.": "Không thể đồng bộ với Màn hình khách hàng.",
    "Customer Display pairing is unavailable.": "Không thể ghép nối với Màn hình khách hàng.",
    "Customer Display was blocked by the browser. Allow pop-ups and try again.": "Trình duyệt đã chặn Màn hình khách hàng. Hãy cho phép cửa sổ bật lên rồi thử lại.",
    "Current order": "Đơn hàng hiện tại",
    "Expected": "Dự kiến",
    "Forbidden": "Không có quyền truy cập",
    "Held carts": "Đơn tạm giữ",
    "ID": "ID",
    "Invalid synchronization envelope input.": "Gói tin đồng bộ không hợp lệ.",
    "Item quick notes": "Ghi chú nhanh cho món",
    "KDS polling interval (ms)": "Khoảng thời gian cập nhật KDS (ms)",
    "Kitchen Display System": "Hệ thống màn hình bếp",
    "Less hot": "Ít nóng",
    "Management": "Quản lý",
    "Member could not be attached.": "Không thể gắn hội viên vào giỏ hàng.",
    "Net revenue ÷ orders": "Doanh thu thuần ÷ số đơn hàng",
    "No closed shifts yet.": "Chưa có ca làm việc nào đã đóng.",
    "No active orders.": "Không có đơn hàng đang xử lý.",
    "No member was found. You can create one below.": "Không tìm thấy hội viên. Bạn có thể tạo hội viên mới bên dưới.",
    "No shift context": "Không có thông tin ca làm việc",
    "No tables are available.": "Không có bàn nào khả dụng.",
    "Open shift": "Mở ca",
    "Opening cash": "Tiền đầu ca",
    "Operational screens": "Màn hình vận hành",
    "Operations": "Vận hành",
    "Order": "Đơn hàng",
    "Order details": "Chi tiết đơn hàng",
    "Order history pages": "Các trang lịch sử đơn hàng",
    "Order Queue polling interval (ms)": "Khoảng thời gian cập nhật Hàng đợi đơn hàng (ms)",
    "orders": "đơn hàng",
    "Orders": "Đơn hàng",
    "orders found": "đơn hàng được tìm thấy",
    "Order type": "Loại đơn hàng",
    "Paid CoffeePOS orders": "Đơn hàng CoffeePOS đã thanh toán",
    "Payment": "Thanh toán",
    "The payment provider is unavailable.": "Nhà cung cấp dịch vụ thanh toán không khả dụng.",
    "Phone is always required and remains privacy-masked on Customer Display.": "Số điện thoại luôn bắt buộc và luôn được che bớt trên Màn hình khách hàng.",
    "Polling intervals are limited to 3000–60000 milliseconds.": "Khoảng thời gian cập nhật được giới hạn từ 3000 đến 60000 mili giây.",
    "Preview change: %s": "Tiền thừa dự kiến: %s",
    "POS route": "Đường dẫn POS",
    "Product configuration unavailable": "Không thể tải cấu hình sản phẩm",
    "The product is invalid or unavailable.": "Sản phẩm không hợp lệ hoặc không khả dụng.",
    "Quick reorder": "Tạo lại đơn nhanh",
    "Ready when you are": "Sẵn sàng phục vụ",
    "Receipt could not be loaded.": "Không thể tải hóa đơn.",
    "Receipt data is incomplete.": "Dữ liệu hóa đơn chưa đầy đủ.",
    "Receipt footer": "Nội dung cuối hóa đơn",
    "Receipt template is unavailable.": "Mẫu hóa đơn không khả dụng.",
    "Report unavailable. Adjust the filters or try again.": "Không thể tải báo cáo. Hãy điều chỉnh bộ lọc hoặc thử lại.",
    "Refresh": "Làm mới",
    "Refund": "Hoàn tiền",
    "Refund order": "Hoàn tiền đơn hàng",
    "Remove": "Xóa",
    "Remove customer from cart": "Gỡ khách khỏi giỏ hàng",
    "Remove item": "Xóa món",
    "Reorder order #%s?": "Tạo lại đơn hàng #%s?",
    "Sales": "Bán hàng",
    "Select logo": "Chọn logo",
    "Select or change table": "Chọn hoặc đổi bàn",
    "Select table": "Chọn bàn",
    "Shift #%s": "Ca #%s",
    "Shift #%s open": "Ca #%s đang mở",
    "Shift request failed.": "Không thể thực hiện yêu cầu về ca làm việc.",
    "Shift unavailable": "Không thể tải thông tin ca làm việc",
    "This report export format is unavailable on the server.": "Máy chủ không hỗ trợ định dạng xuất báo cáo này.",
    "This variation is unavailable.": "Biến thể này không khả dụng.",
    "Store identity comes from General. Core order totals and payment information are always printed.": "Thông tin cửa hàng lấy từ mục Tổng quan. Tổng tiền đơn hàng và thông tin thanh toán luôn được in.",
    "Table 01": "Bàn 01",
    "Table 02": "Bàn 02",
    "Table 03": "Bàn 03",
    "Table 04": "Bàn 04",
    "Table 05": "Bàn 05",
    "Table list": "Danh sách bàn",
    "Tables could not be loaded.": "Không thể tải danh sách bàn.",
    "Loading tables...": "Đang tải danh sách bàn...",
    "Enter one table name per line. Empty lines and duplicate names are ignored.": "Nhập tên mỗi bàn trên một dòng. Dòng trống và tên trùng lặp sẽ được bỏ qua.",
    "Close table selector": "Đóng cửa sổ chọn bàn",
    "Close order note": "Đóng ghi chú đơn hàng",
    "Print the private order note on receipts": "In ghi chú riêng của đơn hàng trên hóa đơn",
    "The cart changed. Review the updated total before submitting.": "Giỏ hàng đã thay đổi. Hãy kiểm tra tổng tiền mới trước khi xác nhận.",
    "The cart is not ready.": "Giỏ hàng chưa sẵn sàng.",
    "The order cannot perform this transition.": "Không thể chuyển đơn hàng sang trạng thái này.",
    "The order changed on another screen. Refresh and try again.": "Đơn hàng đã thay đổi trên màn hình khác. Hãy làm mới và thử lại.",
    "The order could not be reordered.": "Không thể tạo lại đơn hàng.",
    "The order note is invalid or too long.": "Ghi chú đơn hàng không hợp lệ hoặc quá dài.",
    "The QR is displayed on Customer Display. Confirm only after the transfer appears in the bank.": "Mã QR đang hiển thị trên Màn hình khách hàng. Chỉ xác nhận sau khi giao dịch đã xuất hiện trong tài khoản ngân hàng.",
    "The server returned an invalid data envelope.": "Máy chủ trả về gói dữ liệu không hợp lệ.",
    "The shift was not found.": "Không tìm thấy ca làm việc.",
    "Transfer reference prefix": "Tiền tố nội dung chuyển khoản",
    "Updated now": "Vừa cập nhật",
    "Variance": "Chênh lệch",
    "VietQR could not be prepared.": "Không thể tạo mã VietQR.",
    "VietQR template": "Mẫu VietQR",
    "Waiting for Cashier pairing…": "Đang chờ ghép nối từ Thu ngân…",
    "WooCommerce total refunded": "Tổng tiền đã hoàn trong WooCommerce",
    "You are not allowed to print receipts.": "Bạn không có quyền in hóa đơn.",
    "You are not allowed to refund orders.": "Bạn không có quyền hoàn tiền đơn hàng.",
    "You are not allowed to reorder orders.": "Bạn không có quyền tạo lại đơn hàng.",
})

POLISH_REPLACEMENTS = {
    "WooC Commerce": "WooCommerce",
    "Cà phêPOS": "CoffeePOS",
    "cà phêPOS": "CoffeePOS",
    "xe đẩy": "giỏ hàng",
    "Xe đẩy": "Giỏ hàng",
    "đơn đặt hàng": "đơn hàng",
    "Đơn đặt hàng": "Đơn hàng",
    "biên lai": "hóa đơn",
    "Biên lai": "Hóa đơn",
    "phiếu giảm giá": "mã giảm giá",
    "Phiếu giảm giá": "Mã giảm giá",
    "thành viên": "hội viên",
    "Thành viên": "Hội viên",
}


def unquote(value: str) -> str:
    return json.loads(value)


def parse_pot(path: Path) -> list[dict]:
    entries: list[dict] = []
    current: dict = {"refs": []}

    def commit() -> None:
        nonlocal current
        if "msgid" in current and current["msgid"] != "":
            entries.append(current)
        current = {"refs": []}

    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if line.startswith("#: "):
            current["refs"].extend(line[3:].split())
        elif line.startswith("msgid_plural "):
            current["plural"] = unquote(line[len("msgid_plural "):])
        elif line.startswith("msgid "):
            if "msgid" in current:
                commit()
            current["msgid"] = unquote(line[len("msgid "):])
        elif line == "" and "msgid" in current:
            commit()
    commit()
    return entries


def parse_existing_po(path: Path) -> dict[str, str]:
    if not path.is_file():
        return {}
    contents = path.read_text(encoding="utf-8")
    pattern = re.compile(
        r'^msgid (".*")\n(?:msgid_plural .*\n)?msgstr(?:\[0\])? (".*")$',
        re.MULTILINE,
    )
    translations: dict[str, str] = {}
    for source, target in pattern.findall(contents):
        msgid = unquote(source)
        msgstr = unquote(target)
        if msgid and msgstr:
            translations[msgid] = msgstr
    return translations


PLACEHOLDER = re.compile(r"%(?:\d+\$)?[-+0-9.]*[bcdeEfFgGosuxX]|https?://\S+|`[^`]+`|<[^>]+>")


def protect(text: str) -> tuple[str, dict[str, str]]:
    tokens: dict[str, str] = {}

    def replace(match: re.Match) -> str:
        token = f"ZXQTK{len(tokens)}ZXQ"
        tokens[token] = match.group(0)
        return token

    return PLACEHOLDER.sub(replace, text), tokens


def restore(text: str, tokens: dict[str, str]) -> str:
    for token, value in tokens.items():
        text = text.replace(token, value)
        text = text.replace(token.lower(), value)
        text = re.sub(re.escape(token), value, text, flags=re.IGNORECASE)
    return text.strip()


def request_translation(text: str) -> str:
    query = urllib.parse.urlencode({
        "client": "dict-chrome-ex",
        "sl": "en",
        "tl": "vi",
        "q": text,
    })
    request = urllib.request.Request(
        "https://clients5.google.com/translate_a/t?" + query,
        headers={"User-Agent": "Mozilla/5.0 CoffeePOS translation build"},
    )
    for attempt in range(5):
        try:
            with urllib.request.urlopen(request, timeout=30) as response:
                payload = json.loads(response.read().decode("utf-8"))
            return str(payload[0])
        except Exception:
            if attempt == 4:
                raise
            time.sleep(1.5 * (attempt + 1))
    raise RuntimeError("Translation request failed")


def translate(entries: list[dict]) -> dict[str, str]:
    translated = parse_existing_po(PO)
    translated.update(OVERRIDES)
    pending = [entry["msgid"] for entry in entries if entry["msgid"] not in translated]
    chunks: list[list[tuple[str, str, dict[str, str]]]] = []
    chunk: list[tuple[str, str, dict[str, str]]] = []
    size = 0

    for original in pending:
        protected, tokens = protect(original)
        addition = len(protected) + len(SEPARATOR) + 2
        if chunk and (len(chunk) >= 18 or size + addition > 2800):
            chunks.append(chunk)
            chunk = []
            size = 0
        chunk.append((original, protected, tokens))
        size += addition
    if chunk:
        chunks.append(chunk)

    for index, items in enumerate(chunks, 1):
        payload = ("\n" + SEPARATOR + "\n").join(item[1] for item in items)
        result = request_translation(payload)
        parts = re.split(r"\s*" + re.escape(SEPARATOR) + r"\s*", result)
        if len(parts) != len(items):
            parts = [request_translation(item[1]) for item in items]
        for (original, _, tokens), value in zip(items, parts):
            translated[original] = restore(value, tokens)
        print(f"Translated batch {index}/{len(chunks)}", flush=True)

    for entry in entries:
        plural = entry.get("plural")
        if plural and plural not in translated:
            translated[plural] = translated.get(entry["msgid"], entry["msgid"])
    for source, target in list(translated.items()):
        for old, new in POLISH_REPLACEMENTS.items():
            target = target.replace(old, new)
        translated[source] = target
    return translated


def po_quote(value: str) -> str:
    return json.dumps(value, ensure_ascii=False)


def write_po(entries: list[dict], translations: dict[str, str]) -> None:
    header = (
        "Project-Id-Version: CoffeePOS 1.0.0\n"
        "Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/coffeepos/\n"
        "POT-Creation-Date: 2026-08-24 00:00+0000\n"
        "PO-Revision-Date: 2026-08-24 00:00+0000\n"
        "Language: vi\n"
        "MIME-Version: 1.0\n"
        "Content-Type: text/plain; charset=UTF-8\n"
        "Content-Transfer-Encoding: 8bit\n"
        "Plural-Forms: nplurals=1; plural=0;\n"
        "X-Domain: coffeepos\n"
    )
    lines = ["msgid \"\"", "msgstr \"\""]
    lines.extend(po_quote(line + "\n") for line in header.rstrip("\n").split("\n"))
    lines.append("")

    for entry in entries:
        refs = " ".join(entry.get("refs", []))
        if refs:
            lines.append("#: " + refs)
        lines.append("msgid " + po_quote(entry["msgid"]))
        if entry.get("plural"):
            lines.append("msgid_plural " + po_quote(entry["plural"]))
            lines.append("msgstr[0] " + po_quote(translations[entry["msgid"]]))
        else:
            lines.append("msgstr " + po_quote(translations[entry["msgid"]]))
        lines.append("")
    PO.write_text("\n".join(lines), encoding="utf-8", newline="\n")


def write_mo(entries: list[dict], translations: dict[str, str]) -> None:
    header = (
        "Project-Id-Version: CoffeePOS 1.0.0\n"
        "Language: vi\n"
        "MIME-Version: 1.0\n"
        "Content-Type: text/plain; charset=UTF-8\n"
        "Content-Transfer-Encoding: 8bit\n"
        "Plural-Forms: nplurals=1; plural=0;\n"
    )
    catalog: dict[str, str] = {"": header}
    for entry in entries:
        key = entry["msgid"]
        if entry.get("plural"):
            key += "\x00" + entry["plural"]
        catalog[key] = translations[entry["msgid"]]

    keys = sorted(catalog)
    ids = b""
    values = b""
    key_offsets = []
    value_offsets = []
    for key in keys:
        encoded = key.encode("utf-8")
        key_offsets.append((len(encoded), len(ids)))
        ids += encoded + b"\x00"
        encoded_value = catalog[key].encode("utf-8")
        value_offsets.append((len(encoded_value), len(values)))
        values += encoded_value + b"\x00"

    count = len(keys)
    key_table_offset = 7 * 4
    value_table_offset = key_table_offset + count * 8
    ids_offset = value_table_offset + count * 8
    values_offset = ids_offset + len(ids)
    output = struct.pack("<7I", 0x950412DE, 0, count, key_table_offset, value_table_offset, 0, 0)
    output += b"".join(struct.pack("<2I", length, ids_offset + offset) for length, offset in key_offsets)
    output += b"".join(struct.pack("<2I", length, values_offset + offset) for length, offset in value_offsets)
    output += ids + values
    MO.write_bytes(output)


def script_handles() -> dict[str, str]:
    source = (ROOT / "includes" / "Infrastructure" / "Assets" / "AssetLoader.php").read_text(encoding="utf-8")
    pattern = re.compile(r"wp_register_script\(\s*'([^']+)'\s*,\s*COFFEEPOS_URL\s*\.\s*'([^']+\.js)'", re.S)
    return {handle: path for handle, path in pattern.findall(source)}


def write_json(entries: list[dict], translations: dict[str, str]) -> None:
    for old in (ROOT / "languages").glob("coffeepos-vi-coffeepos-*.json"):
        old.unlink()
    by_source: dict[str, list[dict]] = {}
    for entry in entries:
        for reference in entry.get("refs", []):
            source = reference.rsplit(":", 1)[0]
            if source.startswith("assets/js/"):
                by_source.setdefault(source, []).append(entry)

    for handle, source in script_handles().items():
        source_entries = by_source.get(source, [])
        if not source_entries:
            continue
        messages = {
            "": {"domain": "messages", "lang": "vi", "plural-forms": "nplurals=1; plural=0;"}
        }
        for entry in source_entries:
            messages[entry["msgid"]] = [translations[entry["msgid"]]]
        payload = {
            "translation-revision-date": "2026-08-24 00:00+0000",
            "generator": "CoffeePOS Phase 13 translation builder",
            "source": source,
            "domain": "messages",
            "locale_data": {"messages": messages},
        }
        target = ROOT / "languages" / f"coffeepos-vi-{handle}.json"
        target.write_text(json.dumps(payload, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")


def main() -> int:
    entries = parse_pot(POT)
    if not entries:
        raise RuntimeError("POT catalog is empty")
    translations = translate(entries)
    missing = [entry["msgid"] for entry in entries if not translations.get(entry["msgid"], "").strip()]
    if missing:
        raise RuntimeError(f"Missing {len(missing)} translations")
    write_po(entries, translations)
    write_mo(entries, translations)
    write_json(entries, translations)
    print(f"Created Vietnamese catalog with {len(entries)} translated messages.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
