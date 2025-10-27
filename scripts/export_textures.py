#!/usr/bin/env python3
"""
Export textures from PK3 to web-accessible PNG format
"""
import sys
import json
import zipfile
import tempfile
import shutil
import os
from PIL import Image

def export_textures(pk3_path, output_dir):
    """Export all textures from PK3 to PNG"""
    from bsp_tool import load_bsp

    # Extract PK3
    temp_dir = tempfile.mkdtemp(prefix="pk3_export_")

    # Also extract baseq3 pak files for missing textures
    baseq3_dir = tempfile.mkdtemp(prefix="baseq3_")
    pak0_path = os.path.join(os.path.dirname(pk3_path), 'pak0.pk3')

    try:
        with zipfile.ZipFile(pk3_path, 'r') as zf:
            zf.extractall(temp_dir)

        # Extract pak0 if it exists
        if os.path.exists(pak0_path):
            try:
                with zipfile.ZipFile(pak0_path, 'r') as zf:
                    zf.extractall(baseq3_dir)
            except Exception as e:
                print(f"Warning: Could not extract pak0.pk3: {e}", file=sys.stderr)

        # Find BSP
        bsp_path = None
        for root, dirs, files in os.walk(temp_dir):
            for f in files:
                if f.endswith('.bsp'):
                    bsp_path = os.path.join(root, f)
                    map_name = os.path.splitext(f)[0]
                    break
            if bsp_path:
                break

        if not bsp_path:
            return {'error': 'No BSP file found in PK3'}

        # Load BSP
        bsp = load_bsp(bsp_path)

        # Create output directory
        map_output_dir = os.path.join(output_dir, map_name)
        os.makedirs(map_output_dir, exist_ok=True)

        # Export textures
        texture_info = []
        for i, tex in enumerate(bsp.TEXTURES):
            name = tex.name if hasattr(tex, 'name') else str(tex)
            if isinstance(name, bytes):
                name = name.decode('utf-8', errors='ignore')
            clean_name = name.strip().strip('\x00')

            # Skip invisible textures
            if any(x in clean_name.lower() for x in ['clip', 'trigger', 'hint', 'caulk']):
                texture_info.append({
                    'index': i,
                    'name': clean_name,
                    'file': None,
                    'type': 'invisible'
                })
                continue

            # Handle sky textures
            if 'sky' in clean_name.lower() or 'skies/' in clean_name.lower():
                texture_info.append({
                    'index': i,
                    'name': clean_name,
                    'file': None,
                    'type': 'sky'
                })
                continue

            # Try to load and export texture - search in map PK3 first, then baseq3
            tga_path = os.path.join(temp_dir, clean_name + '.tga')
            jpg_path = os.path.join(temp_dir, clean_name + '.jpg')
            baseq3_tga = os.path.join(baseq3_dir, clean_name + '.tga')
            baseq3_jpg = os.path.join(baseq3_dir, clean_name + '.jpg')

            source_path = None
            if os.path.exists(tga_path):
                source_path = tga_path
            elif os.path.exists(jpg_path):
                source_path = jpg_path
            elif os.path.exists(baseq3_tga):
                source_path = baseq3_tga
            elif os.path.exists(baseq3_jpg):
                source_path = baseq3_jpg

            if source_path:
                try:
                    img = Image.open(source_path)
                    if img.mode != 'RGB':
                        img = img.convert('RGB')

                    # Save as PNG
                    output_filename = f"texture_{i}.png"
                    output_path = os.path.join(map_output_dir, output_filename)
                    img.save(output_path, 'PNG')

                    texture_info.append({
                        'index': i,
                        'name': clean_name,
                        'file': output_filename,
                        'width': img.width,
                        'height': img.height,
                        'type': 'texture'
                    })
                except Exception as e:
                    print(f"Error exporting texture {clean_name}: {e}", file=sys.stderr)
                    texture_info.append({
                        'index': i,
                        'name': clean_name,
                        'file': None,
                        'type': 'error'
                    })
            else:
                texture_info.append({
                    'index': i,
                    'name': clean_name,
                    'file': None,
                    'type': 'missing'
                })

        # Export lightmaps
        if hasattr(bsp, 'LIGHTMAPS') and len(bsp.LIGHTMAPS) > 0:
            for i, lightmap in enumerate(bsp.LIGHTMAPS):
                try:
                    # Lightmaps are 128x128 RGB
                    lightmap_data = bytes(lightmap)
                    img = Image.frombytes('RGB', (128, 128), lightmap_data)

                    output_filename = f"lightmap_{i}.png"
                    output_path = os.path.join(map_output_dir, output_filename)
                    img.save(output_path, 'PNG')
                except Exception as e:
                    print(f"Error exporting lightmap {i}: {e}", file=sys.stderr)

        # Save texture metadata to JSON file
        metadata_path = os.path.join(map_output_dir, 'textures.json')
        metadata = {
            'map': map_name,
            'textures': texture_info,
            'output_dir': map_name
        }
        with open(metadata_path, 'w') as f:
            json.dump(metadata, f, indent=2)

        return metadata

    finally:
        shutil.rmtree(temp_dir, ignore_errors=True)
        shutil.rmtree(baseq3_dir, ignore_errors=True)

if __name__ == '__main__':
    if len(sys.argv) < 3:
        print(json.dumps({'error': 'Usage: export_textures.py <pk3_path> <output_dir>'}))
        sys.exit(1)

    pk3_path = sys.argv[1]
    output_dir = sys.argv[2]

    if not os.path.exists(pk3_path):
        print(json.dumps({'error': 'PK3 file not found'}))
        sys.exit(1)

    result = export_textures(pk3_path, output_dir)
    print(json.dumps(result))
