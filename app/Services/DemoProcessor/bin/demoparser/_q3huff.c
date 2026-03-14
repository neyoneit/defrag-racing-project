/*
 * _q3huff.c — C extension for Q3 Huffman decoding
 *
 * Replaces the pure-Python BitStreamReader + Q3HuffmanMapper + Q3HuffmanReader
 * with native C code for ~10-50x speedup on demo parsing.
 *
 * Build: python3 setup.py build_ext --inplace
 */

#define PY_SSIZE_T_CLEAN
#include <Python.h>
#include <structmember.h>
#include <math.h>
#include <string.h>

/* ── Constants ─────────────────────────────────────────────────────── */

#define Q3_HUFFMAN_NYT_SYM  0xFFFFFFFFU
#define FLOAT_INT_BITS      13
#define FLOAT_INT_BIAS      (1 << (FLOAT_INT_BITS - 1))
#define Q3_MAX_STRING_CHARS 1024
#define Q3_BIG_INFO_STRING  8192
#define Q3_PERCENT_CHAR_BYTE 37
#define Q3_DOT_CHAR_BYTE    46
#define MAX_GENTITIES       1024

/* ── Huffman tree ──────────────────────────────────────────────────── */

typedef struct HuffNode {
    struct HuffNode *left;
    struct HuffNode *right;
    unsigned int symbol;
} HuffNode;

/* Pre-allocated node pool (max ~512 nodes needed) */
#define MAX_HUFF_NODES 1024
static HuffNode g_node_pool[MAX_HUFF_NODES];
static int g_node_count = 0;
static HuffNode *g_root = NULL;

static const unsigned short g_symtab[256] = {
    0x0006, 0x003B, 0x00C8, 0x00EC, 0x01A1, 0x0111, 0x0090, 0x007F,
    0x0035, 0x00B4, 0x00E9, 0x008B, 0x0093, 0x006D, 0x0139, 0x02AC,
    0x00A5, 0x0258, 0x03F0, 0x03F8, 0x05DD, 0x07F3, 0x062B, 0x0723,
    0x02F4, 0x058D, 0x04AB, 0x0763, 0x05EB, 0x0143, 0x024F, 0x01D4,
    0x0077, 0x04D3, 0x0244, 0x06CD, 0x07C5, 0x07F9, 0x070D, 0x07CD,
    0x0294, 0x05AC, 0x0433, 0x0414, 0x0671, 0x06F0, 0x03F4, 0x0178,
    0x00A7, 0x01C3, 0x01EF, 0x0397, 0x0153, 0x01B1, 0x020D, 0x0361,
    0x0207, 0x02F1, 0x0399, 0x0591, 0x0523, 0x02BC, 0x0344, 0x05F3,
    0x01CF, 0x00D0, 0x00FC, 0x0084, 0x0121, 0x0151, 0x0280, 0x0270,
    0x033D, 0x0463, 0x06D7, 0x0771, 0x039D, 0x06AB, 0x05C7, 0x0733,
    0x032C, 0x049D, 0x056B, 0x076B, 0x05D3, 0x0571, 0x05E3, 0x0633,
    0x04D7, 0x06CB, 0x0370, 0x02A8, 0x02C7, 0x0305, 0x02EB, 0x01D8,
    0x02F3, 0x013C, 0x03AB, 0x038F, 0x0297, 0x00B0, 0x0141, 0x034F,
    0x005C, 0x0128, 0x02BD, 0x02C4, 0x0198, 0x028F, 0x010C, 0x01B3,
    0x0185, 0x018C, 0x0147, 0x0179, 0x00D9, 0x00C0, 0x0117, 0x0119,
    0x014B, 0x01E1, 0x01A3, 0x0173, 0x016F, 0x00E8, 0x0088, 0x00E5,
    0x005F, 0x00A9, 0x00CC, 0x00FD, 0x010F, 0x0183, 0x0101, 0x0187,
    0x0167, 0x01E7, 0x0157, 0x0174, 0x03CB, 0x03C4, 0x0281, 0x024D,
    0x0331, 0x0563, 0x0380, 0x07D7, 0x042B, 0x0545, 0x046B, 0x043D,
    0x072B, 0x04F9, 0x04E3, 0x0645, 0x052B, 0x0431, 0x07EB, 0x05B9,
    0x0314, 0x05F9, 0x0533, 0x042C, 0x06DD, 0x05C1, 0x071D, 0x05D1,
    0x0338, 0x0461, 0x06E3, 0x0745, 0x066B, 0x04CD, 0x04CB, 0x054D,
    0x0238, 0x07C1, 0x063D, 0x07BC, 0x04C5, 0x07AC, 0x07E3, 0x0699,
    0x07D3, 0x0614, 0x0603, 0x05BC, 0x069D, 0x0781, 0x0663, 0x048D,
    0x0154, 0x0303, 0x015D, 0x0060, 0x0089, 0x07C7, 0x0707, 0x01B8,
    0x03F1, 0x062C, 0x0445, 0x0403, 0x051D, 0x05C5, 0x074D, 0x041D,
    0x0200, 0x07B9, 0x04DD, 0x0581, 0x050D, 0x04B9, 0x05CD, 0x0794,
    0x05BD, 0x0594, 0x078D, 0x0558, 0x07BD, 0x04C1, 0x07DD, 0x04F8,
    0x02D1, 0x0291, 0x0499, 0x06F8, 0x0423, 0x0471, 0x06D3, 0x0791,
    0x00C9, 0x0631, 0x0507, 0x0661, 0x0623, 0x0118, 0x0605, 0x06C1,
    0x05D7, 0x04F0, 0x06C5, 0x0700, 0x07D1, 0x07A8, 0x061D, 0x0D00,
    0x0405, 0x0758, 0x06F9, 0x05A8, 0x06B9, 0x068D, 0x00AF, 0x0064
};

static HuffNode *alloc_node(void) {
    if (g_node_count >= MAX_HUFF_NODES) return NULL;
    HuffNode *n = &g_node_pool[g_node_count++];
    n->left = NULL;
    n->right = NULL;
    n->symbol = Q3_HUFFMAN_NYT_SYM;
    return n;
}

static void put_sym(unsigned int symbol, unsigned int path) {
    HuffNode *node = g_root;
    while (path > 1) {
        if (path & 1) {
            if (!node->right) node->right = alloc_node();
            node = node->right;
        } else {
            if (!node->left) node->left = alloc_node();
            node = node->left;
        }
        path >>= 1;
    }
    node->symbol = symbol;
}

static void init_huffman(void) {
    if (g_root) return;
    g_node_count = 0;
    g_root = alloc_node();
    for (unsigned int i = 0; i < 256; i++) {
        put_sym(i, g_symtab[i]);
    }
}

/* ── BitStream (inlined) ──────────────────────────────────────────── */

typedef struct {
    uint32_t *data;
    Py_ssize_t data_len;     /* number of uint32 words */
    Py_ssize_t bit_length;
    Py_ssize_t bit_idx;
    Py_ssize_t word_idx;
    uint32_t current_bits;
} BitStream;

static void bs_init(BitStream *bs, const uint8_t *buf, Py_ssize_t buflen) {
    bs->bit_length = buflen * 8;
    Py_ssize_t add = (4 - (buflen & 3)) & 3;
    Py_ssize_t padded_len = buflen + add;
    bs->data_len = padded_len / 4;
    bs->data = (uint32_t *)malloc(padded_len);
    if (!bs->data) {
        bs->data_len = 0;
        bs->bit_length = 0;
        return;
    }
    memcpy(bs->data, buf, buflen);
    memset((uint8_t *)bs->data + buflen, 0, add);
    /* convert to little-endian uint32 (data is already LE on x86) */
    bs->bit_idx = 0;
    bs->word_idx = 0;
    bs->current_bits = bs->data_len > 0 ? bs->data[0] : 0;
}

static void bs_free(BitStream *bs) {
    free(bs->data);
    bs->data = NULL;
}

static inline int bs_is_eod(BitStream *bs) {
    return bs->bit_idx >= bs->bit_length;
}

static inline int bs_next_bit(BitStream *bs) {
    if (bs->bit_idx >= bs->bit_length) return -1;
    int result = bs->current_bits & 1;
    bs->bit_idx++;
    if ((bs->bit_idx & 31) >= 1) {
        bs->current_bits >>= 1;
    } else {
        bs->word_idx++;
        bs->current_bits = (bs->word_idx < bs->data_len) ? bs->data[bs->word_idx] : 0;
    }
    return result;
}

static inline uint32_t bs_read_bits(BitStream *bs, int bits) {
    uint32_t value = 0;
    for (int shift = 0; shift < bits; shift++) {
        int bit = bs_next_bit(bs);
        if (bit < 0) break;
        value |= (uint32_t)bit << shift;
    }
    return value;
}

/* ── Huffman decode ────────────────────────────────────────────────── */

static inline int huff_decode_symbol(BitStream *bs) {
    HuffNode *node = g_root;
    while (node && node->symbol == Q3_HUFFMAN_NYT_SYM) {
        int bit = bs_next_bit(bs);
        if (bit < 0) return -1;
        node = (bit == 0) ? node->left : node->right;
    }
    return node ? (int)node->symbol : (int)Q3_HUFFMAN_NYT_SYM;
}

/* ── raw_bits_to_float ─────────────────────────────────────────────── */

static inline float raw_bits_to_float(uint32_t bits) {
    /* Use union for type-punning (standard C99) */
    union { uint32_t u; float f; } conv;
    conv.u = bits;
    return conv.f;
}

/* ── FastHuffmanReader Python type ─────────────────────────────────── */

typedef struct {
    PyObject_HEAD
    BitStream bs;
} FastHuffmanReader;

static void FHR_dealloc(FastHuffmanReader *self) {
    bs_free(&self->bs);
    Py_TYPE(self)->tp_free((PyObject *)self);
}

static int FHR_init(FastHuffmanReader *self, PyObject *args, PyObject *kwds) {
    Py_buffer buf;
    if (!PyArg_ParseTuple(args, "y*", &buf))
        return -1;
    init_huffman();
    bs_init(&self->bs, (const uint8_t *)buf.buf, buf.len);
    PyBuffer_Release(&buf);
    if (!self->bs.data && buf.len > 0) {
        PyErr_NoMemory();
        return -1;
    }
    return 0;
}

static PyObject *FHR_isEOD(FastHuffmanReader *self, PyObject *Py_UNUSED(args)) {
    return PyBool_FromLong(bs_is_eod(&self->bs));
}

static PyObject *FHR_readNumBits(FastHuffmanReader *self, PyObject *args) {
    int bits;
    if (!PyArg_ParseTuple(args, "i", &bits))
        return NULL;

    int neg = 0;
    if (bits < 0) {
        neg = 1;
        bits = -bits;
    }

    int fragment_bits = bits & 7;
    int32_t value = 0;

    if (fragment_bits) {
        value = (int32_t)bs_read_bits(&self->bs, fragment_bits);
        bits -= fragment_bits;
    }

    if (bits) {
        int32_t decoded = 0;
        for (int offset = 0; offset < bits; offset += 8) {
            int sym = huff_decode_symbol(&self->bs);
            if (sym < 0) {
                return PyLong_FromLong(-1);
            }
            decoded |= sym << offset;
        }
        if (fragment_bits) {
            decoded <<= fragment_bits;
        }
        value |= decoded;
    }

    if (neg && bits > 0 && (value & (1 << (bits - 1)))) {
        value |= ~((1 << bits) - 1);
    }

    return PyLong_FromLong(value);
}

static PyObject *FHR_readNumber(FastHuffmanReader *self, PyObject *args) {
    int bits;
    if (!PyArg_ParseTuple(args, "i", &bits))
        return NULL;
    if (bits == 8) {
        int sym = huff_decode_symbol(&self->bs);
        return PyLong_FromLong(sym);
    }
    return FHR_readNumBits(self, args);
}

static PyObject *FHR_readByte(FastHuffmanReader *self, PyObject *Py_UNUSED(args)) {
    return PyLong_FromLong(huff_decode_symbol(&self->bs));
}

static PyObject *FHR_readShort(FastHuffmanReader *self, PyObject *Py_UNUSED(args)) {
    PyObject *a = PyTuple_Pack(1, PyLong_FromLong(16));
    PyObject *r = FHR_readNumBits(self, a);
    Py_DECREF(a);
    return r;
}

static PyObject *FHR_readInt(FastHuffmanReader *self, PyObject *Py_UNUSED(args)) {
    PyObject *a = PyTuple_Pack(1, PyLong_FromLong(32));
    PyObject *r = FHR_readNumBits(self, a);
    Py_DECREF(a);
    return r;
}

static PyObject *FHR_readLong(FastHuffmanReader *self, PyObject *Py_UNUSED(args)) {
    PyObject *a = PyTuple_Pack(1, PyLong_FromLong(32));
    PyObject *r = FHR_readNumBits(self, a);
    Py_DECREF(a);
    return r;
}

/* Faster versions that avoid PyTuple overhead */
static int32_t _readNumBits_fast(BitStream *bs, int bits) {
    int neg = 0;
    if (bits < 0) { neg = 1; bits = -bits; }

    int fragment_bits = bits & 7;
    int32_t value = 0;

    if (fragment_bits) {
        value = (int32_t)bs_read_bits(bs, fragment_bits);
        bits -= fragment_bits;
    }
    if (bits) {
        int32_t decoded = 0;
        for (int offset = 0; offset < bits; offset += 8) {
            int sym = huff_decode_symbol(bs);
            if (sym < 0) return -1;
            decoded |= sym << offset;
        }
        if (fragment_bits) decoded <<= fragment_bits;
        value |= decoded;
    }
    if (neg && bits > 0 && (value & (1 << (bits - 1)))) {
        value |= ~((1 << bits) - 1);
    }
    return value;
}

static PyObject *FHR_readFloat(FastHuffmanReader *self, PyObject *Py_UNUSED(args)) {
    int32_t bits = _readNumBits_fast(&self->bs, 32);
    if (bs_is_eod(&self->bs))
        return PyFloat_FromDouble(-1.0);
    return PyFloat_FromDouble((double)raw_bits_to_float((uint32_t)bits));
}

static PyObject *FHR_readAngle16(FastHuffmanReader *self, PyObject *Py_UNUSED(args)) {
    int32_t v = _readNumBits_fast(&self->bs, 16);
    return PyFloat_FromDouble((double)v * 360.0 / 65536.0);
}

static PyObject *FHR_readFloatIntegral(FastHuffmanReader *self, PyObject *Py_UNUSED(args)) {
    if (bs_read_bits(&self->bs, 1) == 0) {
        int32_t trunc = _readNumBits_fast(&self->bs, FLOAT_INT_BITS);
        trunc -= FLOAT_INT_BIAS;
        return PyFloat_FromDouble((double)trunc);
    }
    int32_t bits = _readNumBits_fast(&self->bs, 32);
    if (bs_is_eod(&self->bs))
        return PyFloat_FromDouble(-1.0);
    return PyFloat_FromDouble((double)raw_bits_to_float((uint32_t)bits));
}

static PyObject *FHR_readData(FastHuffmanReader *self, PyObject *args) {
    PyObject *data_obj;
    int length;
    if (!PyArg_ParseTuple(args, "Oi", &data_obj, &length))
        return NULL;

    if (!PyByteArray_Check(data_obj)) {
        PyErr_SetString(PyExc_TypeError, "expected bytearray");
        return NULL;
    }

    Py_ssize_t dlen = PyByteArray_Size(data_obj);
    char *buf = PyByteArray_AsString(data_obj);
    int count = length < (int)dlen ? length : (int)dlen;
    for (int i = 0; i < count; i++) {
        buf[i] = (char)huff_decode_symbol(&self->bs);
    }
    Py_RETURN_NONE;
}

static PyObject *_readStringBase(FastHuffmanReader *self, int limit, int stop_at_newline) {
    char *chars = (char *)malloc(limit + 1);
    if (!chars) return PyErr_NoMemory();

    int pos = 0;
    for (int i = 0; i < limit; i++) {
        int byte = huff_decode_symbol(&self->bs);
        if (byte <= 0) break;
        if (stop_at_newline && byte == 0x0A) break;
        if (byte > 127 || byte == Q3_PERCENT_CHAR_BYTE)
            byte = Q3_DOT_CHAR_BYTE;
        chars[pos++] = (char)byte;
    }
    chars[pos] = '\0';
    PyObject *result = PyUnicode_FromStringAndSize(chars, pos);
    free(chars);
    return result;
}

static PyObject *FHR_readString(FastHuffmanReader *self, PyObject *Py_UNUSED(args)) {
    return _readStringBase(self, Q3_MAX_STRING_CHARS, 0);
}

static PyObject *FHR_readBigString(FastHuffmanReader *self, PyObject *Py_UNUSED(args)) {
    return _readStringBase(self, Q3_BIG_INFO_STRING, 0);
}

static PyObject *FHR_readStringLine(FastHuffmanReader *self, PyObject *Py_UNUSED(args)) {
    return _readStringBase(self, Q3_MAX_STRING_CHARS, 1);
}

static PyObject *FHR_readServerCommand(FastHuffmanReader *self, PyObject *Py_UNUSED(args)) {
    int32_t seq = _readNumBits_fast(&self->bs, 32);
    PyObject *cmd = _readStringBase(self, Q3_MAX_STRING_CHARS, 0);
    if (!cmd) return NULL;

    PyObject *seq_str = PyUnicode_FromFormat("%d", seq);
    PyObject *dict = PyDict_New();
    PyDict_SetItemString(dict, "sequence", seq_str);
    PyDict_SetItemString(dict, "command", cmd);
    Py_DECREF(seq_str);
    Py_DECREF(cmd);
    return dict;
}


/* ── Method table ──────────────────────────────────────────────────── */

static PyMethodDef FHR_methods[] = {
    {"isEOD",               (PyCFunction)FHR_isEOD,               METH_NOARGS,  NULL},
    {"readNumBits",         (PyCFunction)FHR_readNumBits,         METH_VARARGS, NULL},
    {"readNumber",          (PyCFunction)FHR_readNumber,          METH_VARARGS, NULL},
    {"readByte",            (PyCFunction)FHR_readByte,            METH_NOARGS,  NULL},
    {"readShort",           (PyCFunction)FHR_readShort,           METH_NOARGS,  NULL},
    {"readInt",             (PyCFunction)FHR_readInt,             METH_NOARGS,  NULL},
    {"readLong",            (PyCFunction)FHR_readLong,            METH_NOARGS,  NULL},
    {"readFloat",           (PyCFunction)FHR_readFloat,           METH_NOARGS,  NULL},
    {"readAngle16",         (PyCFunction)FHR_readAngle16,         METH_NOARGS,  NULL},
    {"readFloatIntegral",   (PyCFunction)FHR_readFloatIntegral,   METH_NOARGS,  NULL},
    {"readData",            (PyCFunction)FHR_readData,            METH_VARARGS, NULL},
    {"readString",          (PyCFunction)FHR_readString,          METH_NOARGS,  NULL},
    {"readBigString",       (PyCFunction)FHR_readBigString,       METH_NOARGS,  NULL},
    {"readStringLine",      (PyCFunction)FHR_readStringLine,      METH_NOARGS,  NULL},
    {"readServerCommand",   (PyCFunction)FHR_readServerCommand,   METH_NOARGS,  NULL},
    {NULL}
};

static PyTypeObject FastHuffmanReaderType = {
    PyVarObject_HEAD_INIT(NULL, 0)
    .tp_name = "_q3huff.FastHuffmanReader",
    .tp_basicsize = sizeof(FastHuffmanReader),
    .tp_dealloc = (destructor)FHR_dealloc,
    .tp_flags = Py_TPFLAGS_DEFAULT | Py_TPFLAGS_BASETYPE,
    .tp_doc = "Fast C implementation of Q3HuffmanReader",
    .tp_methods = FHR_methods,
    .tp_init = (initproc)FHR_init,
    .tp_new = PyType_GenericNew,
};

/* ── Module definition ─────────────────────────────────────────────── */

static struct PyModuleDef q3huff_module = {
    PyModuleDef_HEAD_INIT,
    "_q3huff",
    "C extension for Q3 Huffman decoding",
    -1,
    NULL
};

PyMODINIT_FUNC PyInit__q3huff(void) {
    if (PyType_Ready(&FastHuffmanReaderType) < 0)
        return NULL;

    PyObject *m = PyModule_Create(&q3huff_module);
    if (!m) return NULL;

    Py_INCREF(&FastHuffmanReaderType);
    if (PyModule_AddObject(m, "FastHuffmanReader", (PyObject *)&FastHuffmanReaderType) < 0) {
        Py_DECREF(&FastHuffmanReaderType);
        Py_DECREF(m);
        return NULL;
    }

    return m;
}
