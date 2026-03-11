from setuptools import setup, Extension

setup(
    name="_q3huff",
    ext_modules=[
        Extension(
            "_q3huff",
            sources=["_q3huff.c"],
            extra_compile_args=["-O3", "-march=native"],
        )
    ],
)
