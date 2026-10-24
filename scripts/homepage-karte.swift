// Wandelt Speisekarten (PDF, PNG, JPG) in Seitenbilder für die Homepage um.
//
// Aufruf aus dem Projektverzeichnis (nur macOS):
//   swift scripts/homepage-karte.swift "Speisekarte=speisekarte/Zum Stimbeck - Speisekarte.pdf" "Herbstkarte=speisekarte/herbst.png"
//
// Jedes Argument ist "Titel=Datei". Gleicher Titel mehrfach = weitere Seiten derselben Karte.
// Die Reihenfolge der Titel ist die Reihenfolge der Reiter auf der Seite.
// Jeder Aufruf ersetzt den kompletten Inhalt von homepage/karte/.

import AppKit
import Foundation
import PDFKit

let targetWidth: CGFloat = 1400
let jpegQuality = 0.82
let outDir = URL(fileURLWithPath: "homepage/karte", isDirectory: true)

struct Page { let file: String; let width: Int; let height: Int }
struct Menu { let title: String; var pages: [Page] }

func fail(_ message: String) -> Never {
  FileHandle.standardError.write("Fehler: \(message)\n".data(using: .utf8)!)
  exit(1)
}

func slug(_ text: String) -> String {
  let folded = text.lowercased()
    .replacingOccurrences(of: "ä", with: "ae").replacingOccurrences(of: "ö", with: "oe")
    .replacingOccurrences(of: "ü", with: "ue").replacingOccurrences(of: "ß", with: "ss")
  let parts = folded.components(separatedBy: CharacterSet.alphanumerics.inverted).filter { !$0.isEmpty }
  return parts.isEmpty ? "karte" : parts.joined(separator: "-")
}

func writeJPEG(_ cgImage: CGImage, to url: URL) {
  let rep = NSBitmapImageRep(cgImage: cgImage)
  guard let data = rep.representation(using: .jpeg, properties: [.compressionFactor: jpegQuality]) else {
    fail("JPEG konnte nicht erzeugt werden: \(url.lastPathComponent)")
  }
  do { try data.write(to: url) } catch { fail("Schreiben fehlgeschlagen: \(url.path)") }
}

// Zeichnet auf weißen Hintergrund in der Zielbreite.
func render(width: CGFloat, height: CGFloat, draw: (CGContext) -> Void) -> CGImage {
  let w = Int(width.rounded()), h = Int(height.rounded())
  guard let ctx = CGContext(data: nil, width: w, height: h, bitsPerComponent: 8, bytesPerRow: 0,
                            space: CGColorSpace(name: CGColorSpace.sRGB)!,
                            bitmapInfo: CGImageAlphaInfo.noneSkipLast.rawValue) else { fail("Kein Grafikkontext") }
  ctx.setFillColor(CGColor(red: 1, green: 1, blue: 1, alpha: 1))
  ctx.fill(CGRect(x: 0, y: 0, width: w, height: h))
  ctx.interpolationQuality = .high
  draw(ctx)
  return ctx.makeImage()!
}

func pdfPages(_ url: URL) -> [CGImage] {
  guard let doc = PDFDocument(url: url) else { fail("PDF nicht lesbar: \(url.path)") }
  return (0..<doc.pageCount).compactMap { index in
    guard let page = doc.page(at: index) else { return nil }
    let box = page.bounds(for: .cropBox)
    let scale = targetWidth / box.width
    return render(width: targetWidth, height: box.height * scale) { ctx in
      ctx.scaleBy(x: scale, y: scale)
      page.draw(with: .cropBox, to: ctx)
    }
  }
}

func imagePage(_ url: URL) -> CGImage {
  guard let source = CGImageSourceCreateWithURL(url as CFURL, nil),
        let image = CGImageSourceCreateImageAtIndex(source, 0, nil)
  else { fail("Bild nicht lesbar: \(url.path)") }
  let width = min(targetWidth, CGFloat(image.width))
  let height = CGFloat(image.height) * width / CGFloat(image.width)
  return render(width: width, height: height) { ctx in
    ctx.draw(image, in: CGRect(x: 0, y: 0, width: width, height: height))
  }
}

let args = Array(CommandLine.arguments.dropFirst())
if args.isEmpty { fail("Bitte mindestens eine Karte angeben, z. B. \"Speisekarte=speisekarte/karte.pdf\"") }

var menus: [Menu] = []
var inputs: [(title: String, url: URL)] = []
for arg in args {
  guard let eq = arg.firstIndex(of: "=") else { fail("Format \"Titel=Datei\" erwartet: \(arg)") }
  let title = String(arg[..<eq]).trimmingCharacters(in: .whitespaces)
  let path = String(arg[arg.index(after: eq)...])
  let url = URL(fileURLWithPath: path)
  if title.isEmpty { fail("Titel fehlt: \(arg)") }
  if !FileManager.default.fileExists(atPath: url.path) { fail("Datei nicht gefunden: \(path)") }
  inputs.append((title, url))
}

// Ausgabeordner leeren und neu anlegen
try? FileManager.default.removeItem(at: outDir)
do { try FileManager.default.createDirectory(at: outDir, withIntermediateDirectories: true) } catch { fail("Ordner nicht anlegbar: \(outDir.path)") }

for (title, url) in inputs {
  let images: [CGImage]
  switch url.pathExtension.lowercased() {
  case "pdf": images = pdfPages(url)
  case "png", "jpg", "jpeg", "webp", "heic": images = [imagePage(url)]
  default: fail("Nicht unterstütztes Format (PDF, PNG, JPG): \(url.lastPathComponent)")
  }
  if !menus.contains(where: { $0.title == title }) { menus.append(Menu(title: title, pages: [])) }
  let index = menus.firstIndex { $0.title == title }!
  for image in images {
    let number = menus[index].pages.count + 1
    let name = String(format: "%@-%02d.jpg", slug(title), number)
    writeJPEG(image, to: outDir.appendingPathComponent(name))
    menus[index].pages.append(Page(file: "karte/\(name)", width: image.width, height: image.height))
  }
  print("\(title): \(images.count) Seite(n) aus \(url.lastPathComponent)")
}

// Datendatei für die Seite (als Skript, damit es auch über file:// funktioniert)
func js(_ s: String) -> String {
  s.replacingOccurrences(of: "\\", with: "\\\\").replacingOccurrences(of: "\"", with: "\\\"")
}
var lines = ["// Automatisch erzeugt von scripts/homepage-karte.swift. Nicht von Hand bearbeiten.", "window.STIMBECK_KARTEN = ["]
for menu in menus {
  let pages = menu.pages.map { "{ \"src\": \"\(js($0.file))\", \"w\": \($0.width), \"h\": \($0.height) }" }
  lines.append("  { \"titel\": \"\(js(menu.title))\", \"seiten\": [\n    \(pages.joined(separator: ",\n    "))\n  ] },")
}
lines.append("];")
do {
  try (lines.joined(separator: "\n") + "\n").write(to: outDir.appendingPathComponent("karten.js"), atomically: true, encoding: .utf8)
} catch { fail("karten.js nicht schreibbar") }
print("Fertig: \(outDir.path)/karten.js")
