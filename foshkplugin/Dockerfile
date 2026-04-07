# Use official Python Alpine image (lightweight)
FROM python:3.11-alpine

ARG FOSHKPLUGIN_VERSION=0.0.10BetaWN52-fajxo

ENV DEBIAN_FRONTEND=noninteractive
ENV PYTHONPATH=/usr/local/lib/python3.11/site-packages

WORKDIR /opt/foshkplugin

# Copy local FOSHKplugin
COPY foshkplugin.py .

# Install Python dependencies
RUN pip3 install --no-cache-dir --upgrade \
    requests \
    paho-mqtt \
    influxdb \
    pillow \
    influxdb-client

# Patch for broader HTTP response code support
RUN sed -i 's/(200,203)/(200,205)/g' foshkplugin.py

# Create log directory (writable at runtime)
RUN mkdir -p /opt/foshkplugin/logs /opt/foshkplugin/conf /opt/foshkplugin/data && \
    chown -R 1000:1000 /opt/foshkplugin

# Config will be mounted from host — do not embed it here
# /opt/foshkplugin/foshkplugin.conf  →  bind-mount

EXPOSE 8080

ENTRYPOINT ["python3"]
CMD ["foshkplugin.py", "--config", "/opt/foshkplugin/conf/foshkplugin.conf"]
