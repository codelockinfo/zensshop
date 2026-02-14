# TinyMCE Image Alignment Guide

## Overview

We've updated the editor to use **native image alignment**. This works exactly like CKEditor and other standard editors: you just click an image and choose where it should go, and the text wraps around it automatically.

## 🎯 How to Use

### Step 1: Insert Your Content

1. Type your text content as usual.
2. Place your cursor where you want the image.
3. Click the **Image** button and upload/select your image.

### Step 2: Align the Image

1. **Click on the image** to select it.
2. In the popup toolbar or dialog, look for the **"Class"** or **"Image Class"** dropdown.
3. Choose one of the alignment options:

| Option                      | Effect                                                      |
| --------------------------- | ----------------------------------------------------------- |
| **Image Left (Text Right)** | Image floats to the left, text wraps around its right side. |
| **Image Right (Text Left)** | Image floats to the right, text wraps around its left side. |
| **Image Center**            | Image sits in the middle with no text wrapping.             |
| **Full Width**              | Image takes up 100% width.                                  |

### Step 3: That's it!

- The image will move to the selected side.
- Existing text will automatically flow around it.
- You can resize the image by dragging the corners.

---

## 📸 Visual Examples

### **Image Left**

```
┌───────────┐ Text wraps around the
│           │ image here. It flows
│ [ Image ] │ naturally to the right
│           │ side and continues
└───────────┘ below the image if
the text is long enough.
```

### **Image Right**

```
Text wraps around the ┌───────────┐
image here. It flows  │           │
naturally to the left │ [ Image ] │
side and continues    │           │
below the image...    └───────────┘
```

---

## 🔧 Tips for Best Results

1. **Resize Images:** Large images might leave little room for text. Drag the corners to resize them to a good size (e.g. 300px-400px wide).
2. **Spacing:** The system automatically adds 20px spacing between the image and text.
3. **Mobile:** On mobile devices, all images automatically stack vertically (center) for better readability.

---

## 🐛 Troubleshooting

**Q: I don't see the Class dropdown?**
A: Click the image, then click the **Image icon** (Edit Image) in the toolbar. In the dialog that opens, look for the "Class" dropdown.

**Q: Text isn't wrapping?**
A: Make sure you selected "Image Left" or "Image Right". "Image Center" does not wrap text.

**Q: Image is too big?**
A: Click the image and drag the blue corner handles to resize it.

---

**Enjoy the simplified workflow!** 🎉
