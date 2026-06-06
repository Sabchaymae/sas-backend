FROM nginx:1.25-alpine

# Remove default nginx configuration
RUN rm /etc/nginx/conf.d/default.conf

# Copy custom API Gateway configuration
COPY nginx.conf /etc/nginx/conf.d/

# Expose port 80
EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]