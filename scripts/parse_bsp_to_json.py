#!/usr/bin/env python3
"""
Parse Quake 3 BSP from PK3 and output JSON for Three.js
"""
import sys
import json
import zipfile
import tempfile
import shutil
import os
from PIL import Image

def parse_bsp_to_json(pk3_path):
    """Parse BSP and return JSON data for Three.js"""
    # Import here to avoid issues if not installed
    try:
        from bsp_tool import load_bsp
    except ImportError:
        return {'error': 'bsp_tool not installed'}

    # Extract PK3
    temp_dir = tempfile.mkdtemp(prefix="pk3_parse_")

    try:
        with zipfile.ZipFile(pk3_path, 'r') as zf:
            zf.extractall(temp_dir)

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

        # Get vertices with colors and UVs
        vertices = []
        vertex_colors = []
        vertex_uvs = []
        for v in bsp.VERTICES:
            vertices.append([v.position.x, v.position.y, v.position.z])
            # Get vertex color (lightmap already baked in)
            if hasattr(v, 'colour'):
                vertex_colors.append([v.colour.r, v.colour.g, v.colour.b])
            else:
                vertex_colors.append([255, 255, 255])
            # Get texture UV coordinates
            if hasattr(v, 'uv') and hasattr(v.uv, 'texture'):
                vertex_uvs.append([v.uv.texture.u, v.uv.texture.v])
            else:
                vertex_uvs.append([0, 0])

        # Get textures
        textures = []
        for tex in bsp.TEXTURES:
            name = tex.name if hasattr(tex, 'name') else str(tex)
            if isinstance(name, bytes):
                name = name.decode('utf-8', errors='ignore')
            textures.append(name)

        # Load texture colors
        texture_colors = {}
        texture_types = {}  # Track special texture types
        for i, tex_name in enumerate(textures):
            clean_name = tex_name.strip().strip('\x00')

            # Identify special textures but don't skip them
            if 'sky' in clean_name.lower() or 'skies/' in clean_name.lower():
                texture_colors[i] = [135, 206, 235]  # Sky blue
                texture_types[i] = 'sky'
                continue
            elif any(x in clean_name.lower() for x in ['clip', 'trigger', 'hint', 'caulk']):
                # Skip truly invisible surfaces
                texture_types[i] = 'invisible'
                continue

            # Try to load texture
            full_path = os.path.join(temp_dir, clean_name + '.tga')
            if os.path.exists(full_path):
                try:
                    img = Image.open(full_path)
                    if img.mode != 'RGB':
                        img = img.convert('RGB')

                    # Get average color
                    small = img.resize((8, 8))
                    pixels = list(small.getdata())
                    avg_r = sum(p[0] for p in pixels) // len(pixels)
                    avg_g = sum(p[1] for p in pixels) // len(pixels)
                    avg_b = sum(p[2] for p in pixels) // len(pixels)

                    texture_colors[i] = [avg_r, avg_g, avg_b]
                except:
                    pass

        # Get indices (mesh verts)
        indices = []
        if hasattr(bsp, 'INDICES'):
            indices = list(bsp.INDICES)

        # Process faces - convert to triangles using indices
        faces_data = []
        for face in bsp.FACES:
            # Get texture index
            tex_idx = face.texture if hasattr(face, 'texture') else -1

            # Skip invisible surfaces
            if tex_idx in texture_types and texture_types[tex_idx] == 'invisible':
                continue

            # Get texture color
            if tex_idx in texture_colors:
                color = texture_colors[tex_idx]
            else:
                # Default gray
                color = [128, 128, 140]

            # Check if face uses indices (mesh vertices)
            num_mesh_verts = getattr(face, 'num_mesh_vertices', 0)

            if num_mesh_verts > 0 and len(indices) > 0:
                # Face uses indices - create triangles from indices
                first_mesh_vert = getattr(face, 'first_mesh_vertex', 0)
                first_vertex = getattr(face, 'first_vertex', 0)

                # Process triangles (every 3 indices makes a triangle)
                for i in range(0, num_mesh_verts, 3):
                    if i + 2 >= num_mesh_verts:
                        break

                    triangle_verts = []
                    triangle_uvs = []
                    for j in range(3):
                        idx_pos = first_mesh_vert + i + j
                        if idx_pos < len(indices):
                            vert_idx = first_vertex + indices[idx_pos]
                            if vert_idx < len(vertices):
                                triangle_verts.append(vertices[vert_idx])
                                triangle_uvs.append(vertex_uvs[vert_idx])

                    if len(triangle_verts) == 3:
                        face_data = {
                            'vertices': triangle_verts,
                            'uvs': triangle_uvs,  # UV coordinates for texture mapping
                            'texture': tex_idx,  # Texture index
                            'color': color  # Fallback color
                        }

                        if tex_idx in texture_types and texture_types[tex_idx] == 'sky':
                            face_data['type'] = 'sky'

                        faces_data.append(face_data)
            else:
                # Face doesn't use indices - use vertices directly (fallback)
                num_verts = getattr(face, 'num_vertices', 0)
                face_verts = []
                face_uvs = []
                first_vertex = getattr(face, 'first_vertex', 0)

                for j in range(num_verts):
                    vert_idx = first_vertex + j
                    if vert_idx < len(vertices):
                        face_verts.append(vertices[vert_idx])
                        face_uvs.append(vertex_uvs[vert_idx])

                if len(face_verts) >= 3:
                    face_data = {
                        'vertices': face_verts,
                        'uvs': face_uvs,
                        'texture': tex_idx,
                        'color': color
                    }

                    if tex_idx in texture_types and texture_types[tex_idx] == 'sky':
                        face_data['type'] = 'sky'

                    faces_data.append(face_data)

        # Build result
        result = {
            'name': map_name,
            'faces': faces_data,
            'bounds': {
                'min': [
                    min(v[0] for v in vertices),
                    min(v[1] for v in vertices),
                    min(v[2] for v in vertices)
                ],
                'max': [
                    max(v[0] for v in vertices),
                    max(v[1] for v in vertices),
                    max(v[2] for v in vertices)
                ]
            }
        }

        return result

    finally:
        shutil.rmtree(temp_dir, ignore_errors=True)

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'No PK3 path provided'}))
        sys.exit(1)

    pk3_path = sys.argv[1]

    if not os.path.exists(pk3_path):
        print(json.dumps({'error': 'PK3 file not found'}))
        sys.exit(1)

    result = parse_bsp_to_json(pk3_path)
    print(json.dumps(result))
