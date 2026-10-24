# MartPro Hardware Peripherals & POS Setup Guide

## 1. Barcode Scanners (USB / Bluetooth)
- **Supported Devices:** Any standard 1D/2D handheld or in-counter presentation scanner (Honeywell, Zebra, Datalogic, Inateck, Netum).
- **Communication Protocol:** USB HID Keyboard Emulation.
- **Scanner Configuration:** Program your scanner to send a Carriage Return / Enter (`CR` or `0x0D`) suffix after every barcode scan.
- **Testing:** Navigate to `modules/hardware/index.php` and scan any barcode in the live scanner test box.

## 2. Thermal Receipt Printers (58mm / 80mm)
- **Supported Devices:** Epson TM-T88VI, TM-T20, Star Micronics TSP100, Rongta, Bixolon, Xprinter.
- **Roll Sizes:** 80mm (Standard 3 1/8") and 58mm (Compact 2 1/4").
- **Setup:** Configure the printer in Windows / CUPS drivers. In MartPro `modules/settings/`, select your default paper width (80mm or 58mm).
- **Silent Printing:** In Chrome or Edge, enable Kiosk Mode flag `--kiosk-printing` on POS terminals to automatically bypass the print confirmation dialog.

## 3. Electronic Cash Drawers (RJ11 / RJ12)
- **Setup:** Connect the drawer's RJ11/RJ12 cable directly to the DK (Drawer Kick) port on the back of the thermal receipt printer.
- **Trigger:** When completing a sale or clicking "Kick Drawer", the thermal printer receives standard ESC/POS pulse command `ESC p 0 25 250` (or driver automatic kick) to pop open the drawer latch.

## 4. Electronic Weighing Scales (RS-232 / Web Serial)
- **Supported Scales:** Datalogic Magellan, Toledo, Avery Berkel, CAS, Torrey.
- **Baud Rate:** 9600 bps, 8 Data Bits, 1 Stop Bit, No Parity (9600 8N1).
- **Browser Connection:** In Google Chrome or Microsoft Edge, click **Connect Scale via Web Serial** in `modules/hardware/` and grant permission to the USB-to-Serial COM port.
- **Embedded Barcode Alternative:** Scales that print barcode labels with prefix `20` (e.g. `20 01001 01450 6`) encode the 5-digit PLU and grams weight directly into the 13-digit EAN barcode, which MartPro POS decodes automatically upon scan.

## 5. Dual Customer-Facing Displays
- **Setup:** Connect a secondary screen to the POS terminal (HDMI/VGA).
- **Window:** Navigate to `modules/hardware/customer_display.php` and drag the window to the customer-facing monitor. It provides a real-time mirror of scanned items and payable totals.
